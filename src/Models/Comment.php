<?php
namespace Leazycms\FLC\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
class Comment extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'link',
        'email',
        'content',
        'comment_meta',
        'pinned',
        'status'
    ];
    protected $casts=[
        'comment_meta' => 'array',
    ];
    public function commentable()
    {
        return $this->morphTo();
    }
    public function user(){
        return $this->belongsTo(get_class(Auth::user()));
    }
    public function reference()
    {
        // Pastikan nilai dari commentable_type adalah nama model dengan namespace lengkap
        $modelClass = $this->commentable_type;

        if (class_exists($modelClass)) {
            // Menggunakan belongsTo dengan commentable_id sebagai foreign key
            return $this->belongsTo($modelClass, 'commentable_id', 'id');
        }

        // Jika model tidak ditemukan, bisa kembalikan null atau lakukan exception handling
        return null;
    }
    public function child(){
        return $this->hasMany(Comment::class, 'parent_id', 'id');
    }

}
