<?php
namespace Leazycms\FLC\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Leazycms\FLC\Traits\Fileable;
use Illuminate\Support\Facades\Auth;

class File extends Model
{
    use Fileable;
    protected $fillable = ['file_path', 'file_type','file_auth','file_name','file_size','purpose','child_id','user_id','host'];
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
        Storage::delete($this->file_path);
        $this->delete();
    }
}
