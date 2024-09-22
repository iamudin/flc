<?php
namespace Leazycms\FLC\Traits;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Leazycms\FLC\Models\File;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;

trait Fileable
{
    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }
    private function is_mime_type(array $mime_type){
        return array_reduce($mime_type, function($carry, $item) {
            return $carry && preg_match('/^[\w\.\-]+\/[\w\.\-]+$/', $item);
        }, true);
    }
    public function addFile(array $source)
    {
        if(!is_array($source)){
            return null;
        }
        $file = isset($source['file']) &&  is_file($source['file']) ? $source['file'] : null;
        $purpose = isset($source['purpose']) && is_string($source['purpose']) && strlen($source['purpose']) > 0 ? str($source['purpose'])->slug() : null;
        $childId = isset($source['child_id']) && (is_string($source['child_id']) || is_numeric($source['child_id'])) && strlen($source['child_id'])>0 ? $source['child_id'] : null;
        $auth = isset($source['auth']) && is_numeric($source['auth']) ? $source['auth'] : null;
        $mime = isset($source['mime_type']) && is_array($source['mime_type']) && $this->is_mime_type($source['mime_type'])? $source['mime_type'] : null;
        $width = isset($source['width']) && is_numeric($source['width']) ? $source['width'] : null;
        $height = isset($source['height']) && is_numeric($source['height']) ? $source['height'] : null;

        $self_upload = isset($source['self_upload']) ? true : false;
        if($file===null && $purpose===null && $mime===null){
            return null;
        }
        if (!in_array($file->getMimeType(),$mime)) {
            // MIME type tidak diizinkan, jangan lakukan apa-apa dan kembalikan null
            return null;
        }
        $this->removeFileByPurposeAndChild($purpose, $childId);
        $upload = $this->handleFileUpload($file,$width,$height);
        $mimeType = $file->getMimeType();

        $data = [
            'user_id' => auth()?->id(),
            'file_path' => $upload->path,
            'file_type' => $mimeType,
            'file_auth' => $auth,
            'file_name' => $upload->name,
            'file_size' => Storage::size($upload->path),
            'purpose' => $purpose,
            'host' => request()->getHttpHost(),
            'child_id' => $childId,
        ];
        if($self_upload){
            $data['fileable_type'] = self::class;
            $data['fileable_id'] = 3;
            $id = $this->insertGetId($data);
            $this->whereId($id)->update(['fileable_id'=>$id,'created_at'=>now()]);
            $file= $this->find($id);
        }else{
            $file = $this->files()->create($data);

        }
        Cache::remember("media_{$file->file_name}", 60 * 60 * 24, function () use ($file) {
            return json_decode(json_encode([
                'file_path' => $file->file_path,
                'file_type' => $file->file_type,
                'file_host' => $file->host,
                'file_auth' => $file->file_auth,
            ]));
        });
        return '/media/'.$upload->name;
    }
    private function handleFileUpload($file,$width=null,$height=null)
    {
        // Dapatkan tanggal sekarang
        $datePath = Carbon::now()->format('Y/m/d');

        // Tentukan direktori penyimpanan berdasarkan tanggal
        $directory = Str::after(config('filesystems.disks.local.root'),'app/')."/files/{$datePath}";

        // Buat nama file baru yang di-*slug* dan ditambahkan dengan string acak
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $sluggedName = str($originalName)->slug();
        $fileName = $sluggedName . '-' . str()->random(6) . '.' . $file->getClientOriginalExtension();

        // Cek apakah file adalah gambar
        if (str_starts_with($file->getMimeType(), 'image/')) {
            // Kompres gambar menggunakan Intervention Image
            $image = Image::make($file);
            $image->resize($width ?? 1000, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Simpan gambar yang sudah dikompres ke storage
            $pathimage = $directory . '/' . $fileName;
            Storage::put($pathimage, (string) $image->encode());
            $path = Storage::path($directory . '/' . $fileName);

        } else {
            // Simpan file non-gambar langsung ke storage dengan nama yang sudah di-*slug*
            $path = $file->storeAs($directory, $fileName);
        }

        return json_decode(json_encode(['path'=>$path,'name'=>$fileName]));
    }


    public function removeFileByPurposeAndChild($purpose, $childId = null)
    {
        $query = $this->files()->where('purpose', $purpose);

        if ($childId !== null) {
            $query->where('child_id', $childId);
        }
        $existingFile = $query->first();
        if ($existingFile) {
        Cache::forget('media_'.$existingFile->file_name);
            $existingFile->deleteFile(); // Menghapus file dari storage dan record dari database
        }
    }
}
