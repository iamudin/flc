<?php
namespace Leazycms\FLC\Traits;
use Carbon\Carbon;
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
        $file = isset($source['file']) && is_file($source['file']) ? $source['file'] : null;
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
        $ext = $file->getClientOriginalExtension();
        if (!in_array($file->getMimeType(),$mime) && !in_array($ext,flc_ext())) {
            // MIME type tidak diizinkan, jangan lakukan apa-apa dan kembalikan null
            return null;
        }
        try{
        $this->removeFileByPurposeAndChild($purpose, $childId,$self_upload);
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
            'disk' => config('filesystems.default'),
            'host' => request()->getHost(),
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
        Cache::rememberForever("media_{$file->file_name}", function () use ($file) {
            return json_decode(json_encode([
                'file_path' => $file->file_path,
                'file_type' => $file->file_type,
                'file_host' => $file->host,
                'file_auth' => $file->file_auth,
                'file_size' => $file->file_size,
                'file_hits' => $file->file_hits,
                'file_disk' => $file->disk,
            ]));
        });
        return '/media/'.$upload->name;
    }
catch(\Exception $e){


}
    }
    private function handleFileUpload($file,$width=null,$height=null)
    {


        $directory =  (config('flc.disk_directory') ? config('flc.disk_directory').'/' : request()->getHost().'/').Carbon::now()->format('Y/m/d');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $sluggedName = str($originalName)->slug();
        $extension = str($file->getClientOriginalExtension())->lower();
        $fileName = $sluggedName.'.' . $file->getClientOriginalExtension();
        if(File::whereFileName($sluggedName.'.' . $extension)->exists()){
            $fileName = $sluggedName . '-' . str(str()->random(4))->lower() . '.' . $extension;
        }
        if (!in_array($extension,flc_ext())) {

            // MIME type tidak diizinkan, jangan lakukan apa-apa dan kembalikan null
            return null;
        }
        // Cek apakah file adalah gambar
        try {
            if (str_starts_with($file->getMimeType(), 'image/') && strpos($file->getMimeType(), 'gif') === false) {

                // Kompres gambar menggunakan Intervention Image
            $image = Image::make($file);
            $image->resize($width ?? 1000, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
                // Ubah extension dan MIME type menjadi WebP jika bukan WebP
                $fileNameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
                $finalFileName = $fileNameWithoutExt . '.webp';
                $path = $directory . '/' . $finalFileName;
                // Simpan gambar dalam format WebP
                $imageData = (string) $image->encode('webp', 80); // kualitas 80
                Storage::put($path, $imageData);
            } else {
                $path = $file->storeAs($directory, $fileName);
                $finalFileName = $fileName;
        }
        } catch (\Exception $e) {
            return back()->send()->with('success',$e);
        }
        return json_decode(json_encode(['path'=>$path,'name'=>$finalFileName]));
    }


    public function removeFileByPurposeAndChild($purpose, $childId = null,$self_upload=false)
    {
        if($self_upload){
            $query = $this->where('purpose', $purpose);
        }else{
            $query = $this->files()->where('purpose', $purpose);

        }

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
