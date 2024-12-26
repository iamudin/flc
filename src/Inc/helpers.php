<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

if (!function_exists('flc_file_manager')) {
    function flc_file_manager()
    {
        if (!auth()->check()) {
            return 'Not Authorized';
        }
        $data = \Leazycms\FLC\Models\File::with('user')->whereHost(request()->getHost())->latest()->paginate(10);
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
    function flc_comment_form($attr=false)
    {   if($data = config('modules.data')){
            $attribute = array(
                'email'=> isset($attr['email']) && $attr['email'] !== true ? false : true,
                'link'=>isset($attr['link']) && $attr['link'] !== true ? false : true,
                'content'=>isset($attr['content']) && $attr['content'] !== true ? false : true,
                'comment_meta'=> isset($attr['comment_meta']) && is_array($attr['comment_meta'])  ? $attr['comment_meta'] : null,
            );
            if($data->allow_comment=='Y'){
        $data = $data->load('comments');
        return \Illuminate\Support\Facades\View::make('flc::comment_form', ['comments' => paginate($data->comments->where('status','publish')->sortByDesc('created_at'),10),'attribute'=>$attribute ?? []]);
    }

}

    }
}
if (!function_exists('media_size')) {
    function media_size($media)
    {
        $media_exists =  \Illuminate\Support\Facades\Cache::get("media_" . basename($media)) ?? null;
        return $media_exists && isset($media_exists->file_path) && \Illuminate\Support\Facades\Storage::exists($media_exists->file_path) ? size_as_kb($media_exists->file_size)  : null;
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
if (!function_exists('media_download')) {
    function media_download($media)
    {
        $media_exists =  \Illuminate\Support\Facades\Cache::get("media_" . basename($media)) ?? null;
        return $media_exists && isset($media_exists->file_path) && \Illuminate\Support\Facades\Storage::exists($media_exists->file_path) ? url($media . '?download=' . md5(request()->session()->getId())) : false;
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
        return $media_exists && isset($media_exists->file_path) && \Illuminate\Support\Facades\Storage::exists($media_exists->file_path) ? true : false;
    }
}

if (!function_exists('media_caching')) {
    function media_caching()
    {
        foreach (\Leazycms\FLC\Models\File::select('file_path', 'file_name', 'file_type', 'file_size', 'file_hits', 'file_auth', 'host')->get() as $row) {
            if (Storage::exists($row->file_path)) {
                Cache::remember("media_{$row->file_name}", 60 * 60 * 24, function () use ($row) {
                    return json_decode(json_encode([
                        'file_path' => $row->file_path,
                        'file_type' => $row->file_type,
                        'file_host' => $row->host,
                        'file_auth' => $row->file_auth,
                        'file_size' => $row->file_size,
                    ]));
                });
            }
        }
        Cache::remember("media", 60 * 60 * 24, function () {
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
