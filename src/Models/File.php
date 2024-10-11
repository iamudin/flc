<?php
namespace Leazycms\FLC\Models;
use Leazycms\FLC\Traits\Fileable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use Fileable;
    protected $fillable = ['file_path', 'file_type','file_auth','file_name','file_size','purpose','child_id','user_id','host','file_hits'];
    protected $casts = ['created_at'=>'datetime'];

    public function fileable()
    {
        return $this->morphTo();
    }

    public function user(){
        return $this->belongsTo(get_class(Auth::user()));
    }
    public function deleteFile()
    {
        if( Storage::exists($this->file_path)){
        Storage::delete($this->file_path);
        }
        Cache::forget('media_'.$this->file_name);
        $this->delete();
    }
}
