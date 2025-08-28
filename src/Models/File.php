<?php
namespace Leazycms\FLC\Models;
use Leazycms\Web\Models\User;
use Leazycms\FLC\Traits\Fileable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use Fileable;
    protected $fillable = ['file_path', 'file_type','file_auth','file_name','file_size','purpose','child_id','user_id','host','file_hits','disk','fileable_type','fileable_id'];
    protected $casts = ['created_at'=>'datetime'];

    public function fileable()
    {
        return $this->morphTo();
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function deleteFile()
    {
        if( Storage::disk($this->disk)->exists($this->file_path)){
        Storage::disk($this->disk)->delete($this->file_path);
        }
        Cache::forget('media_'.$this->file_name);
        $this->delete();
    }
}
