<?php
if(!function_exists('flc_file_manager')){
    function flc_file_manager(){
        if(!auth()->check()){
            return 'Not Authorized';
        }
        $data = \Leazycms\FLC\Models\File::with('user')->whereHost(request()->getHost())->latest()->paginate(10);
        return \Illuminate\Support\Facades\View::make('flc::index',['data'=>$data]);
    }
}
if(!function_exists('flc_ext')){
    function flc_ext(){
        return ['jpg','jpeg','gif','zip','rar','doc','docx','pdf','xls','xlsx','png','webp'];
    }
}
