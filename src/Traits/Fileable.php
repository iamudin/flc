<?php
namespace Leazycms\FLC\Traits;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Auth;
use Leazycms\FLC\Models\File;


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
        $is_encrypt = !empty($source['is_encrypt']);
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
        $upload = $this->handleFileUpload($file,$width,$height,$is_encrypt);
        $mimeType = $file->getMimeType();
        $disk = config('filesystems.default');
        $data = [
            'user_id' => auth()?->id(),
            'file_path' => $upload->path,
            'file_type' => $mimeType,
            'file_auth' => $auth,
            'file_name' => $upload->name,
            'encrypt_key' => $upload->encrypt_key ?? null,
            'file_size' => Storage::disk($disk)->size($upload->path),
            'purpose' => $purpose,
            'disk' => $disk,
            'host' => app()->has('tenant') && isset($this->tenant_id) !== tenant()->id ? $this->tenant->domain ?? request()->getHost() : request()->getHost(),
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
        Cache::rememberForever("media:{$file->file_name}", function () use ($file) {
            return [
                'file_path' => $file->file_path,
                'file_type' => $file->file_type,
                'file_host' => $file->host,
                'file_auth' => $file->file_auth,
                'file_size' => $file->file_size,
                'file_hits' => $file->file_hits,
                'file_disk' => $file->disk,
                'encrypt_key' => $file->encrypt_key,
            ];
        });
        return '/media/'.$upload->name;
    }
catch(\Exception $e){
    Log::channel('daily')->error('File upload error: ' . $e->getMessage(), [
        'info' => 'Error during file upload',
        'ip' => get_client_ip(),
        'url' => request()->fullUrl(),
    ]);
}
    }
    private function handleFileUpload($file,$width=null,$height=null,$shouldEncrypt=false)
    {

        $host = app()->has('tenant') && isset($this->tenant_id) !== tenant()->id ? $this->tenant->domain ?? request()->getHost() : request()->getHost();
        $directory =  (config('flc.disk_directory') ? config('flc.disk_directory').'/' : $host.'/').Carbon::now()->format('Y/m/d');
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
        $disk = config('filesystems.default');
        $masterKey = config('flc.encrypt_key');
        $shouldEncrypt = $shouldEncrypt && is_string($masterKey) && trim((string) $masterKey) !== '';
        $encryptedKeyForDb = null;
        $fileKey = null;
        if ($shouldEncrypt) {
            $fileKey = 'base64:' . base64_encode(random_bytes(32));
            $encryptedKeyForDb = encryptData($masterKey, $fileKey);
        }
        // Cek apakah file adalah gambar
        try {
            if (str_starts_with($file->getMimeType(), 'image/') && strpos($file->getMimeType(), 'gif') === false && strpos($file->getMimeType(), 'icon') === false) {

                // Kompres gambar menggunakan Intervention Image
            $image = Image::make($file);
            $image->resize($width ?? 1200, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
                // Ubah extension dan MIME type menjadi WebP jika bukan WebP
                $fileNameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
                $finalFileName = $fileNameWithoutExt . '.webp';
                $path = $directory . '/' . $finalFileName;
                // Simpan gambar dalam format WebP
                $imageData = (string) $image->encode('webp', 95); // kualitas 80
                if ($shouldEncrypt) {
                    $imageData = encryptData($fileKey, $imageData);
                }
                Storage::disk($disk)->put($path, $imageData);
            } else {
                $path = $directory . '/' . $fileName;
                $finalFileName = $fileName;
                if ($shouldEncrypt) {
                    $contents = file_get_contents($file->getRealPath());
                    $contents = encryptData($fileKey, $contents);
                    Storage::disk($disk)->put($path, $contents);
                } else {
                    $path = $file->storeAs($directory, $fileName, $disk);
                }
        }
        } catch (\Exception $e) {
            Log::channel('daily')->error('File upload error: ' . $e->getMessage(), [
                'info' => 'Error during file upload',
                'ip' => get_client_ip(),
                'url' => request()->fullUrl(),
            ]);
        }
        Log::channel('daily')->warning('File uploaded: '.url("media:{$finalFileName}"), [
            'path' => $path,
            'ip' => get_client_ip(),
            'user_id' => Auth::user()->email,
            'url' => request()->fullUrl(),
            'referer' => request()->headers->get('referer'),

        ]);
        return json_decode(json_encode(['path'=>$path,'name'=>$finalFileName,'encrypt_key'=>$encryptedKeyForDb]));
    }


    public function removeFileByPurposeAndChild(string $purpose, $childId = null,$self_upload=false)
    {
        if($self_upload){
            $query = collect($this)->where('purpose', $purpose);
        }else{
            $query = $this->files()->where('purpose', $purpose);

        }

        if ($childId !== null) {
            $query->where('child_id', $childId);
        }
        $existingFile = $query->first();
        if ($existingFile) {
            Cache::forget('media:'.$existingFile->file_name);
            Log::channel('daily')->warning('File deleted: ' . $existingFile->file_name, [
                'path' => $existingFile->file_path,
                'ip' => get_client_ip(),
                'user_id' => Auth::user()->email,
                'referer' => request()->headers->get('referer'),
            ]);
            $existingFile->deleteFile(); // Menghapus file dari storage dan record dari database

        }
    }
}
