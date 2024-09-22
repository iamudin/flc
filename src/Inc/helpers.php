<?php
if(!function_exists('flc_file_manager')){
    function flc_file_manager(){
        if(!auth()->check()){
            return 'Not Authorized';
        }
        $data = \Leazycms\FLC\Models\File::with('user')->whereHost(request()->getHost())->latest()->paginate(10);
        return \Illuminate\Support\Facades\View::make('flc::files',['data'=>$data]);
    }
}
if(!function_exists('flc_comment')){
    function flc_comment(){
        if(!auth()->check()){
            return 'Not Authorized';
        }
        $data = \Leazycms\FLC\Models\Comment::with('user')->latest()->paginate(10);
        return \Illuminate\Support\Facades\View::make('flc::comments',['data'=>$data]);
    }
}
if(!function_exists('flc_ext')){
    function flc_ext(){
        return ['jpg','jpeg','gif','zip','rar','doc','docx','pdf','xls','xlsx','png','webp','mp4'];
    }
}

if(!function_exists('flc_file_size')){
    function flc_file_size($fileName){
        $file = \Illuminate\Support\Facades\Cache::get("media_".basename($fileName))?->file_path;
        if($file){
            return size_as_kb(\Illuminate\Support\Facades\Storage::size($file));
        }
    }
}

