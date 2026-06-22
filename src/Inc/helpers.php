<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
if (!function_exists('getMimeTypeByExtension')) {
function getMimeTypeByExtension(string $filename) {
     $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $mimeTypes = [
        // Dokumen
        'txt'  => 'text/plain',
        'htm'  => 'text/html',
        'html' => 'text/html',
        'css'  => 'text/css',
        'csv'  => 'text/csv',
        'xml'  => 'application/xml',
        'json' => 'application/json',
        'pdf'  => 'application/pdf',

        // Microsoft Office lama
        'doc'  => 'application/msword',
        'xls'  => 'application/vnd.ms-excel',
        'ppt'  => 'application/vnd.ms-powerpoint',

        // Microsoft Office baru (OOXML)
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

        // OpenDocument
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp'  => 'application/vnd.oasis.opendocument.presentation',

        // Gambar
        'jpg'  => 'image/jpeg',
        'ico'  => 'image/x-icon',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'bmp'  => 'image/bmp',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',

        // Arsip
        'zip'  => 'application/zip',
        'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg',
        'rar'  => 'application/vnd.rar',
        'gz'   => 'application/gzip',
        'tar'  => 'application/x-tar',
        '7z'   => 'application/x-7z-compressed',
    ];

    return $mimeTypes[$ext] ?? 'application/octet-stream';
}
}


if (!function_exists('flc_file_manager')) {
    function flc_file_manager()
    {
        if (!Auth::check()) {
            return 'Not Authorized';
        }
        $data = \Leazycms\FLC\Models\File::with('user')->latest()->paginate(10);
        return \Illuminate\Support\Facades\View::make('flc::files', ['data' => $data]);
    }
}
if (!function_exists('flc_comment')) {
    function flc_comment()
    {
        if (!Auth::check()) {
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
            if($form_open=='Y'){
                if($attr){
                    $attr = (array) $attr;
                }
            $attribute = array(
                'email'=> isset($attr['email']) && $attr['email'] !== true ? false : true,
                'link'=>isset($attr['link']) && $attr['link'] !== true ? false : true,
                'comment_content'=>isset($attr['comment_content']) && $attr['comment_content'] !== true ? false : true,
                'comment_meta'=> isset($attr['comment_meta']) && is_array($attr['comment_meta'])  ? $attr['comment_meta'] : null,
            );
            $data = $data->load([
                'comments.user',          // user komentar utama
                'comments.childs.user', // user anak komentar
                'comments.childs.childs.user', // jika ingin dukung hingga 3 level
                'comments.childs.childs.childs.user', // jika ingin dukung hingga 3 level
                'comments.childs.childs.childs.childs.user', // jika ingin dukung hingga 3 level
            ]);
        Session::put('captcha',str()->random(6));
        return \Illuminate\Support\Facades\View::make('flc::comment_form', ['title'=>$title,'allow_comment'=>$form_open,'comments' => paginate($data->comments->where('status','publish')->sortByDesc('created_at'),10),'attribute'=>$attribute ?? []]);
            }

}

    }
}

if (!function_exists('media')) {
    function media($media,$host=null)
    {
        return \Leazycms\FLC\Inc\MediaHandler::getInstance($media,$host);
    }
}

if (!function_exists('get_ext')) {
    function get_ext($file)
    {
        if (!empty($file)) :
            $file_name = $file;
            $temp = explode('.', $file_name);
            $extension = end($temp);
            return $extension;
        else :
            return false;
        endif;
    }
}
if (!function_exists('media_extension')) {
    function media_extension(string $media)
    {
        return media($media)->extension();
    }
}
if (!function_exists('size_as_kb')) {
    function size_as_kb(int $bytes, $precision = 2)
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
        return 'audio/mpeg,application/x-zip-compressed,application/zip,image/jpeg,image/png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,video/mp4,image/gif,image/webp,image/x-icon,image/vnd.microsoft.icon';
    }
}



if (!function_exists('media_stream')) {
    function media_stream($media)
    {
        return media($media)->stream();
    }
}
if (!function_exists('media_download')) {
    function media_download($media)
    {
        return media($media)->download();
    }
}

if (!function_exists('media_hits')) {
    function media_hits($name)
    {
        return media($name)->hits();
    }
}

if (!function_exists('media_exists')) {
    function media_exists($media='')
    {
        return media($media)->isExists();
    }
}

if (!function_exists('media_path')) {
    function media_path($media)
    {
        return media($media)->path();
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
        'file'=> request()->file('file'),
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
        $query = \Leazycms\FLC\Models\File::select('file_path', 'file_name', 'file_type', 'file_size', 'file_hits', 'file_auth', 'host', 'disk');

        if (config('modules.multisite_enabled')) {
            if (app()->has('tenant')) {
                $query->where('host', tenant()->domain);
            }
        }

        $query = $query->get();

        $cachedCount = 0;
        foreach ($query as $row) {
            $disk = $row->disk ?: config('filesystems.default');
            if ($disk && Storage::disk($disk)->exists($row->file_path)) {
                Cache::rememberForever($row->host.":media:{$row->file_name}",function () use ($row) {
                    return [
                        'file_path' => $row->file_path,
                        'file_type' => $row->file_type,
                        'file_host' => $row->host,
                        'file_auth' => $row->file_auth,
                        'file_size' => $row->file_size,
                        'file_hits' => $row->file_hits,
                        'encrypt_key' => $row->encrypt_key,
                        'file_disk' => $row->disk ? $row->disk : config('filesystems.default'),
                    ];
                });
                $cachedCount++;
            }
        }
        Cache::rememberForever(get_current_host().":media:all", function () {
            return true;
        });

        return [
            'cache_key' => get_current_host().":media:all",
            'total_rows' => $query->count(),
            'cached' => $cachedCount,
        ];
    }
}

if (!function_exists('flc_ext')) {
    function flc_ext()
    {
        return ['jpg', 'jpeg', 'gif', 'zip', 'rar', 'doc', 'docx', 'pdf', 'xls', 'xlsx', 'png', 'webp', 'mp4','mp3','ico'];
    }
}


if (!function_exists('media_viewer')) {
    function media_viewer(string $media, $height = 600)
    {
        $id = 'viewer_' . md5($media . uniqid());
        $fileUrl = media_stream($media) ?? $media;

        $ext = strtolower(pathinfo($media, PATHINFO_EXTENSION));

        // === TYPE DETECTION ===
        $imageExt = ['jpg','jpeg','png','gif','webp'];
        $officeExt = ['doc','docx','xls','xlsx','ppt','pptx'];
        $pdfExt = ['pdf'];

        // === IMAGE ===
        if (in_array($ext, $imageExt)) {
            return "
            <div style='text-align:center;'>
                <img src='{$fileUrl}' style='max-width:100%; height:auto;' />
            </div>
            ";
        }

        // === OFFICE FILE (Microsoft Viewer) ===
        if (in_array($ext, $officeExt)) {
            $officeUrl = "https://view.officeapps.live.com/op/embed.aspx?src=" . urlencode(url($media));

            return "
            <iframe
                src='{$officeUrl}'
                width='100%'
                height='{$height}'
                style='border:none;'>
            </iframe>
            ";
        }

        // === PDF / DEFAULT (Google Viewer + fallback) ===
        if (in_array($ext, $pdfExt)) {
            $pdfUrl = e($fileUrl);
            $pdfPreviewUrl = !is_local()
                ? 'https://docs.google.com/gview?url=' . urlencode($fileUrl) . '&embedded=true'
                : $fileUrl;

            return "
            <div id='{$id}_wrapper' style='width:100%;'>

                <div id='{$id}_loading' style='text-align:center; padding:20px;'>
                    Memuat preview...
                </div>

                <iframe
                    id='{$id}_iframe'
                    src=''
                    width='100%'
                    height='{$height}'
                    style='border:none; display:none;'>
                </iframe>

            </div>

            <script>
            (function(){
                const iframe = document.getElementById('{$id}_iframe');
                const loading = document.getElementById('{$id}_loading');

                let loaded = false;
                let switched = false;

                const previewUrl = '{$pdfPreviewUrl}';

                iframe.src = previewUrl;

                iframe.onload = function () {
                    if (!switched) {
                        loaded = true;
                        loading.style.display = 'none';
                        iframe.style.display = 'block';
                    }
                };

                setTimeout(function () {
                    if (!loaded) {
                        switched = true;

                        // fallback ke file langsung
                        iframe.src = '{$pdfUrl}';

                        loading.style.display = 'none';
                        iframe.style.display = 'block';
                    }
                }, " . (!is_local() ? "6000" : "0") . ");

            })();
            </script>
            ";
        }

        // === DEFAULT (tidak bisa preview) ===
        return "
        <div style='text-align:center; padding:20px;'>
            <p>Preview file tidak tersedia.</p>
        </div>
        ";
    }
}
if (!function_exists('encryptData')) {
function encryptData($key,$contents)
{
    $key = base64_decode(
        str_replace('base64:', '', $key)
    );

    $iv = random_bytes(16);

    $encrypted = openssl_encrypt(
        $contents,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return base64_encode($iv . $encrypted);
}
}
if (!function_exists('decryptData')) {
function decryptData($key,$encryptedData)
{
    $key = base64_decode(
        str_replace('base64:', '', $key)
    );

    $data = base64_decode($encryptedData);

    $iv = substr($data, 0, 16);

    $encrypted = substr($data, 16);

    return openssl_decrypt(
        $encrypted,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
}
}
if (!function_exists('media_size')) {
    function media_size(string $fileName)
    {
        return media($fileName)->size();
    }
}
if (!function_exists('flc_file_to_path')) {
    function flc_file_to_path(string $fileName)
    {
        $file = json_decode(json_encode(\Illuminate\Support\Facades\Cache::get("media:" . basename($fileName))))?->file_path;
        if ($file) {
            return Storage::path($file);
        }
    }
}
