<?php
namespace Leazycms\FLC\Traits;

use Leazycms\FLC\Models\Comment;

trait Commentable
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function addComment(array $source)
    {
        $data['content'] = $source['content'] ?? null;
        $data['name'] = $source['name'] ?? null;
        $data['email'] = $source['email'] ?? null;
        $data['ip'] = $source['ip'] ?? null;
        $data['link'] = $source['link'] ?? null;
        $data['reference'] = request()->fullUrl();
        $data['comment_meta'] = $source['comment_meta'] ?? null;
        $data['user_id'] = auth()?->id();
        $data['parent_id'] = $source['parent_id'] ?? null;
        if(isset($source['self_comment'])){
            $data['commentable_type'] = self::class;
            $data['commentable_id'] = 3;
            $id = $this->insertGetId($data);
            $this->whereId($id)->update(['commentable_id'=>$id,'created_at'=>now()]);
        }else{
            $this->comments()->create($data);
        }
    }

}
