<?php
if(!function_exists('lw_file_manager')){
    function lw_file_manager(){
        if(!auth()->check()){
            return 'Not Authorized';
        }
        $data = \Leazycms\FLC\Models\File::with('user')->whereHost(request()->getHost())->latest()->paginate(10);
        return \Illuminate\Support\Facades\View::make('file-manager::index',['data'=>$data]);
    }
}
if(!function_exists('lw_ext')){
    function lw_ext(){
        return ['jpg','jpeg','gif','zip','rar','doc','docx','pdf','xls','xlsx','png','webp'];
    }
}
