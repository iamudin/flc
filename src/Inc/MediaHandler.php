<?php

namespace Leazycms\FLC\Inc;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class MediaHandler
{
    protected $media;
    protected $host;
    protected $data = null;
    protected $exists = null;
    protected static $instances = [];

    public function __construct($media, $host = null)
    {
        $this->media = $media;
        $this->host = $host ?? get_current_host();
    }

    public static function getInstance($media, $host = null)
    {
        if (empty($media)) return new self(null, $host);
        $instanceHost = $host ?? get_current_host();
        $key = $instanceHost . ":" . basename($media);
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($media, $host);
        }
        return self::$instances[$key];
    }

    public function getData()
    {
        return $this->loadData();
    }

    protected function loadData()
    {
        if ($this->media === null) return null;
        if ($this->data === null) {
            $key = basename($this->media);

            // Proteksi: Jika key mengandung spasi atau karakter aneh yang biasanya bukan filename asli
            if (str_contains($key, ' ') || strlen($key) < 3) {
                $this->data = false;
                return null;
            }

            // Cek media:{name} (format utama)
            $cacheKey = $this->host . ":media:" . $key;
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                if ($cached instanceof \__PHP_Incomplete_Class) {
                    Cache::forget($this->host . ":media:{$key}");
                    $cached = null;
                } elseif (is_object($cached)) {
                    $cached = json_decode(json_encode($cached), true);
                }
                if (is_array($cached)) {
                    $this->data = json_decode(json_encode($cached));
                } else {
                    $this->data = $cached; // Bisa berupa object atau false
                }
            }

            // Jika tidak ada di cache (bernilai null), baru cek database
            if ($this->data === null) {
                $file = \Leazycms\FLC\Models\File::select('file_path', 'file_type', 'file_size', 'file_hits', 'file_auth', 'host', 'disk', 'encrypt_key')
                    ->whereFileName($key)
                    ->where('host', $this->host)
                    ->first();

                if ($file && Storage::disk($file->disk)->exists($file->file_path)) {
                    $this->data = [
                        'file_path' => $file->file_path,
                        'file_type' => $file->file_type,
                        'file_host' => $file->host,
                        'file_auth' => $file->file_auth,
                        'file_size' => $file->file_size,
                        'file_hits' => $file->file_hits,
                        'file_disk' => $file->disk,
                        'encrypt_key' => $file->encrypt_key,
                    ];
                    Cache::forever($cacheKey, $this->data);
                } else {
                    // Tandai sebagai false agar tidak query berulang jika file tidak ada
                    $this->data = false;
                    // Cache juga status "tidak ada" ini selama 1 jam agar tidak hit DB terus-menerus
                    Cache::put($cacheKey, false, now()->addHour());
                }
            }
        }
        if ($this->data === false) {
            return null;
        }
        if (is_array($this->data)) {
            return json_decode(json_encode($this->data));
        }
        return is_object($this->data) ? $this->data : null;
    }

    public function size()
    {
        if ($this->media === null) return '0 KB';
        $data = $this->loadData();
        if ($data && isset($data->file_size) && $data->file_size !== null) {
            if (is_numeric($data->file_size) && (int) $data->file_size > 0) {
                return size_as_kb((int) $data->file_size);
            }
        }

        if ($data && isset($data->file_disk) && isset($data->file_path)) {
            try {
                if (Storage::disk($data->file_disk)->exists($data->file_path)) {
                    $size = Storage::disk($data->file_disk)->size($data->file_path);
                    if (is_numeric($size)) {
                        $size = (int) $size;
                        if ($size > 0) {
                            $key = basename($this->media);
                            $cacheData = is_object($data) ? (array) $data : (is_array($data) ? $data : []);
                            $cacheData['file_size'] = $size;
                            $cacheKey = $this->host . ":media:{$key}";
                            Cache::forever($cacheKey, $cacheData);
                            $this->data = $cacheData;
                            \Leazycms\FLC\Models\File::whereFileName($key)
                                ->where('file_path', $cacheData['file_path'] ?? null)
                                ->where('disk', $cacheData['file_disk'] ?? null)
                                ->where('host', $this->host)
                                ->update(['file_size' => $size]);
                        }
                        return size_as_kb($size);
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return '0 KB';
    }

    public function extension()
    {
        if ($this->media === null || !$this->isExists()) {
            return 'N/A';
        }
        $data = $this->loadData();
        return str($data->file_ext ?? get_ext($this->media))->upper();
    }

    public function isExists()
    {
        if ($this->media === null) return false;
        if ($this->exists === null) {
            $data = $this->loadData();
            $this->exists = (is_object($data) && !($data instanceof \__PHP_Incomplete_Class) && isset($data->file_path) && isset($data->file_disk));
        }
        return $this->exists;
    }

    public function path()
    {
        if ($this->isExists()) {
            $data = $this->loadData();
            return Storage::disk($data->file_disk)->path($data->file_path);
        }
        return false;
    }

    public function disk()
    {
        $data = $this->loadData();
        return $data->file_disk ?? config('filesystems.default');
    }

    public function mime()
    {
        if ($this->media === null) return 'application/octet-stream';
        $data = $this->loadData();
        return $data->file_type ?? getMimeTypeByExtension($this->media);
    }

    public function hits()
    {
        if ($this->media === null) return 0;
        $data = $this->loadData();
        return $data->file_hits ?? 0;
    }
    public function getUrl()
    {
        if ($this->isExists()) {
            $data = $this->loadData();
            $url = "/media/" . basename($this->media);
            if ($data->file_host && $data->file_host != request()->getHost()) {
                $url = "http://" . $data->file_host . $url;
            }
            return $url;
        }
        return noimage();
    }

    public function url()
    {
        return $this->getUrl();
    }

    public function stream()
    {
        if ($this->isExists()) {
            return route('media.stream', enc64(enc64(basename($this->media))));
        }
        return false;
    }

    public function download()
    {
        if ($this->isExists()) {
            return route('media.download', [enc64(basename($this->media)), md5(session()->getId())]);
        }
        return false;
    }

    public function embed($height = 600)
    {
        $id = 'viewer_' . md5($this->media . uniqid());
        $fileUrl = $this->stream() ?? $this->media;

        $ext = strtolower(pathinfo($this->media, PATHINFO_EXTENSION));

        // === TYPE DETECTION ===
        $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $officeExt = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
        $pdfExt = ['pdf'];

        // === IMAGE ===
        if (in_array($ext, $imageExt)) {
            return "
            <div style='text-align:center;'>
                <img src='{$fileUrl}' style='width:100%; height:auto;' />
            </div>
            ";
        }

        // === OFFICE FILE (Microsoft Viewer) ===
        if (in_array($ext, $officeExt)) {
            $officeUrl = "https://view.officeapps.live.com/op/embed.aspx?src=" . urlencode(url($this->media));

            return "
            <iframe
                src='{$officeUrl}'
                width='100%'
                height='{$height}'
                style='border:none;'>
            </iframe>
            ";
        }

        // === PDF / DEFAULT (Google Viewer + fallback) ===
        if (in_array($ext, $pdfExt)) {
            $pdfUrl = e($fileUrl);
            $pdfPreviewUrl = !is_local()
                ? 'https://docs.google.com/gview?url=' . urlencode($fileUrl) . '&embedded=true'
                : $fileUrl;

            return "
            <div id='{$id}_wrapper' style='width:100%;'>

                <div id='{$id}_loading' style='text-align:center; padding:20px;'>
                    Memuat preview...
                </div>

                <iframe
                    id='{$id}_iframe'
                    src=''
                    width='100%'
                    height='{$height}'
                    style='border:none; display:none;'>
                </iframe>

            </div>

            <script>
            (function(){
                const iframe = document.getElementById('{$id}_iframe');
                const loading = document.getElementById('{$id}_loading');

                let loaded = false;
                let switched = false;

                const previewUrl = '{$pdfPreviewUrl}';

                iframe.src = previewUrl;

                iframe.onload = function () {
                    if (!switched) {
                        loaded = true;
                        loading.style.display = 'none';
                        iframe.style.display = 'block';
                    }
                };

                setTimeout(function () {
                    if (!loaded) {
                        switched = true;

                        // fallback ke file langsung
                        iframe.src = '{$pdfUrl}';

                        loading.style.display = 'none';
                        iframe.style.display = 'block';
                    }
                }, " . (!is_local() ? "6000" : "0") . ");

            })();
            </script>
            ";
        }

        // === DEFAULT (tidak bisa preview) ===
        return "
        <div style='text-align:center; padding:20px;'>
            <p>Preview file tidak tersedia.</p>
        </div>
        ";
    }
}
