<?php
namespace Leazycms\FLC\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Leazycms\FLC\Models\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class FileManagerController extends Controller implements HasMiddleware
{
    public static function middleware(): array {
        return [
            new Middleware('auth',only: ['upload','destroy'])
        ];
    }

    public function upload(Request $request){

        abort_if(!$request->user() || !$request->isMethod('post'),'404');
            $request->validate([
                'media' =>'required|mimetypes:'.allow_mime(),
             ]);
            if( $file = $request->file('media')){

               if( (new File)->addFile([
                    'file'=>$file,
                    'purpose'=>'Upload Media',
                    'child_id'=>str()->random(6),
                    'mime_type'=> explode(',',allow_mime()),
                    'self_upload'=>true
                ])!==null){
                    return back()->with('success','File berhasil diupload');

                }
            }

    }
    public function destroy(Request $request){
        abort_if(!$request->user() || !$request->isMethod('post'),404);
        if($request->media){
            $media = $request->media;
            $data = File::whereFileName(basename($media))->first();
            if($data){
                Cache::forget("media_".basename($media));
                Storage::delete($data->file_path);
                $data->forceDelete();
            }
        }
        }
    public function stream_by_id($slug)
    {
        $media = Cache::remember("media_{$slug}", 60 * 60 * 24, function () use ($slug) {
            $file = File::select('file_path', 'file_type','file_size', 'file_hits','file_auth','host')
                ->whereFileName($slug)
                ->first();

                if($file && Storage::exists($file->file_path)){
                    return json_decode(json_encode([
                        'file_path' => $file->file_path,
                        'file_type' => $file->file_type,
                        'file_host' => $file->host,
                        'file_auth' => $file->file_auth,
                        'file_size' => $file->file_size,
                    ]));
                }
                return null;
        });
        abort_if(empty($media) ||  (isset($media->file_host) && request()->getHost()!=$media->file_host || !Storage::exists($media->file_path)),404);
        $auth = $media->file_auth;
        if ($auth === null) {
        } elseif ($auth == 0) {
            abort_if(!auth()->check(), 403, 'You need to be logged in to access this resource.');
        } elseif ($auth > 0) {
            abort_if($auth != auth()->id(), 403, 'You do not have permission to access this resource.');
        }
        if($id = request('download')){
            if($id!= md5(request()->session()->getId())){
                abort('403','Link Expired');
            }
            File::whereFilePath($media->file_path)->select('id')->increment('file_hits');
            return response()->download(Storage::path($media->file_path));
        }
        return response()->stream(function () use ($media) {
            $stream = Storage::readStream($media->file_path);
            abort_if($stream === false, 404);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->file_type,
            'Content-Disposition' => 'inline; filename="' . basename($media->file_path) . '"',
            'Cache-Control' => 'public, max-age=31536000, immutable'
        ]);
    }
}
