<?php
namespace Leazycms\FLC\Models;
use Leazycms\Web\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'ip',
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
        return $this->belongsTo(User::class);
    }
    public function post()
    {
        return $this->belongsTo(query(), 'commentable_id');
    }
    public function childs(){
        return $this->hasMany(Comment::class, 'parent_id', 'id');
    }

}
