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
        'reference',
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
    public function post()
    {
        return $this->belongsTo(query(), 'commentable_id');
    }
    public function childs(){
        return $this->hasMany(Comment::class, 'parent_id', 'id');
    }

}
