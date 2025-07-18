<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

if (!function_exists('flc_file_manager')) {
    function flc_file_manager()
    {
        if (!auth()->check()) {
            return 'Not Authorized';
        }
        $data = \Leazycms\FLC\Models\File::with('user')->latest()->paginate(10);
        return \Illuminate\Support\Facades\View::make('flc::files', ['data' => $data]);
    }
}
if (!function_exists('flc_comment')) {
    function flc_comment()
    {
        if (!auth()->check()) {
            return 'Not Authorized';
        }
        $data = \Leazycms\FLC\Models\Comment::with('user')->latest()->paginate(10);
        return \Illuminate\Support\Facades\View::make('flc::comments', ['data' => $data]);
    }
}
if (!function_exists('flc_comment_form')) {
    function flc_comment_form($title='Komentar', $attr=false)
    {   if($data = config('modules.data')){
            $form_open = $data->allow_comment;
            $attribute = array(
                'email'=> isset($attr['email']) && $attr['email'] !== true ? false : true,
                'link'=>isset($attr['link']) && $attr['link'] !== true ? false : true,
                'content'=>isset($attr['content']) && $attr['content'] !== true ? false : true,
                'comment_meta'=> isset($attr['comment_meta']) && is_array($attr['comment_meta'])  ? $attr['comment_meta'] : null,
            );
            $data = $data->load([
                'comments.user',          // user komentar utama
                'comments.childs.user', // user anak komentar
                'comments.childs.childs.user', // jika ingin dukung hingga 3 level
                'comments.childs.childs.childs.user', // jika ingin dukung hingga 3 level
                'comments.childs.childs.childs.childs.user', // jika ingin dukung hingga 3 level
            ]);
        session()->put('captcha',str()->random(6));
        return \Illuminate\Support\Facades\View::make('flc::comment_form', ['title'=>$title,'allow_comment'=>$form_open,'comments' => paginate($data->comments->where('status','publish')->sortByDesc('created_at'),10),'attribute'=>$attribute ?? []]);

}

    }
}
if (!function_exists('media_size')) {
    function media_size($media)
    {
        $media_exists =  \Illuminate\Support\Facades\Cache::get("media_" . basename($media)) ?? null;
        return $media_exists && isset($media_exists->file_path) && \Illuminate\Support\Facades\Storage::disk($media_exists->file_disk)->exists($media_exists->file_path) ? size_as_kb($media_exists->file_size)  : null;
    }
}
if (!function_exists('size_as_kb')) {
    function size_as_kb($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('allow_mime')) {

    function allow_mime()
    {
        return 'application/x-zip-compressed,application/zip,image/jpeg,image/png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream,video/mp4,image/gif';
    }
}
if (!function_exists('media_download')) {
    function media_download($media)
    {
        $media_exists =  \Illuminate\Support\Facades\Cache::get("media_" . basename($media)) ?? null;
        return $media_exists && isset($media_exists->file_path) && \Illuminate\Support\Facades\Storage::disk($media_exists->file_disk)->exists($media_exists->file_path) ? url($media . '?download=' . md5(request()->session()->getId())) : false;
    }
}

if (!function_exists('media_hits')) {
    function media_hits(array $id)
    {
        return \Leazycms\FLC\Models\File::whereIn('fileable_id', $id)->pluck('file_hits', 'file_name')->toArray();
    }
}

if (!function_exists('media_exists')) {
    function media_exists($media)
    {
        $media_exists =  \Illuminate\Support\Facades\Cache::get("media_" . basename($media)) ?? null;
        return $media_exists && isset($media_exists->file_path) && \Illuminate\Support\Facades\Storage::disk($media_exists->file_disk)->exists($media_exists->file_path) ? true : false;
    }
}
function media_capture(){
    $p = collect(Cache::get('url_data',[]))->first();
    if($p){
    $post = query()->find($p['post_id']);
    $postIdToRemove = $post->id;
    $url = 'https://'.$p['url'];
    $response = \Illuminate\Support\Facades\Http::get(config('flc.capture_api'), [
        'url' => $url,
    ]);
    $data =  $response->json();
    $tmpFilePath = storage_path('app/'.str($url)->slug().'.jpg');
    $fileData = base64_decode($data['image_base64']);
    file_put_contents($tmpFilePath, $fileData);

    // Buat instance UploadedFile
    $uploadedFile = new \Illuminate\Http\UploadedFile(
        $tmpFilePath, // Path ke file
        str($url)->slug().'.jpg', // Nama file
        'image/jpeg', // MIME type
        null, // Error (null berarti tidak ada)
        true // Set sebagai test mode
    );
    request()->files->set('file', $uploadedFile);
    $capture = $post->addFile([
        'file'=>request()->file('file'),
        'purpose'=>'capture-web',
        'mime_type'=> ['image/jpeg']
    ]);
    $jsonData = $post->data_field;
// Tambahkan key baru 'capture'
    $jsonData['capture'] = $capture;

// Update kolom JSON di database
    $post->data_field = $jsonData;
    $post->save();
    unlink($tmpFilePath);

    $data = Cache::get('url_data', []);
// Menggunakan Collection untuk menghapus data berdasarkan post_id
$updatedData = collect($data)->reject(function ($p) use ($postIdToRemove) {
return $p['post_id'] == $postIdToRemove;
})->values()->all(); // Menggunakan values() untuk reindex dan all() untuk mengembalikan array
Cache::forget('url_data');
Cache::rememberForever('url_data', function()use($updatedData){
return $updatedData;
});
}

}
if (!function_exists('url_capture')) {
    function url_capture($post,$url)
    {
        $newPost = [
            'post_id' => $post->id, // post_id yang sama dengan yang ada di dalam array
            'url' => parse_url($url, PHP_URL_HOST),
            'created_at' => time(),
        ];
        // Mengambil data dari cache
        $data = Cache::get('url_data', []);

        // Menggunakan Collection untuk memeriksa apakah 'post_id' sudah ada
            $existingPostIds = collect($data)->pluck('post_id')->toArray();

            if (!in_array($newPost['post_id'], $existingPostIds)) {
                // Jika post_id belum ada, tambahkan data baru
                $data[] = $newPost;

                // Menyimpan kembali data yang sudah diperbarui ke cache
                Cache::forget('url_data');
                Cache::rememberForever('url_data', function()use($data){
                    return $data;
                });
            }

    }
}
if (!function_exists('media_caching')) {
    function media_caching()
    {
        foreach (\Leazycms\FLC\Models\File::select('file_path', 'file_name', 'file_type', 'file_size', 'file_hits', 'file_auth', 'host')->get() as $row) {
            if (Storage::disk($row->file_disk)->exists($row->file_path)) {
                Cache::rememberForever("media_{$row->file_name}",function () use ($row) {
                    return json_decode(json_encode([
                        'file_path' => $row->file_path,
                        'file_type' => $row->file_type,
                        'file_host' => $row->host,
                        'file_auth' => $row->file_auth,
                        'file_size' => $row->file_size,
                        'file_disk' => $row->disk,
                    ]));
                });
            }
        }
        Cache::rememberForever("media", function () {
            return true;
        });
    }
}

if (!function_exists('flc_ext')) {
    function flc_ext()
    {
        return ['jpg', 'jpeg', 'gif', 'zip', 'rar', 'doc', 'docx', 'pdf', 'xls', 'xlsx', 'png', 'webp', 'mp4'];
    }
}

if (!function_exists('flc_file_size')) {
    function flc_file_size($fileName)
    {
        $file = \Illuminate\Support\Facades\Cache::get("media_" . basename($fileName))?->file_path;
        if ($file) {
            return size_as_kb(\Illuminate\Support\Facades\Storage::size($file));
        }
    }
}
if (!function_exists('flc_file_to_path')) {
    function flc_file_to_path($fileName)
    {
        $file = \Illuminate\Support\Facades\Cache::get("media_" . basename($fileName))?->file_path;
        if ($file) {
            return Storage::path($file);
        }
    }
}
