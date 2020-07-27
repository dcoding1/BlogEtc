<?php

namespace WebDevEtc\BlogEtc\Controllers;

use WebDevEtc\BlogEtc\Middleware\UserCanManageBlogPosts;
use WebDevEtc\BlogEtc\Models\BlogEtcPost;
use WebDevEtc\BlogEtc\Models\BlogEtcUploadedPhoto;
use WebDevEtc\BlogEtc\Requests\UploadImageRequest;
use WebDevEtc\BlogEtc\Traits\UploadFileTrait;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use File;

/**
 * Class BlogEtcAdminController
 * @package WebDevEtc\BlogEtc\Controllers
 */
class BlogEtcImageUploadController extends Controller
{
    use UploadFileTrait;

    /**
     * BlogEtcAdminController constructor.
     */
    public function __construct()
    {
        $this->middleware(UserCanManageBlogPosts::class);

        if (!is_array(config("blogetc"))) {
            throw new \RuntimeException('The config/blogetc.php does not exist. Publish the vendor files for the BlogEtc package by running the php artisan publish:vendor command');
        }

        if (!config("blogetc.image_upload_enabled")) {
            throw new \RuntimeException("The blogetc.php config option has not enabled image uploading");
        }
    }

    /**
     * Show the main listing of uploaded images
     * @return mixed
     */
    public function index()
    {
        return view("blogetc_admin::imageupload.index", [
            'uploaded_photos' => BlogEtcUploadedPhoto::orderBy("id", "desc")->paginate(10)
        ]);
    }

    /**
     * Show the main listing of uploaded images for froala editor.
     * @return mixed
     */
    public function indexFloara()
    {
        $images = [];

        $dir = config("blogetc.blog_upload_dir");
        $files = Storage::allFiles($dir);

        if (!empty($files)) {
            foreach ($files as $file) {
                if (strstr($file, 'thumb.')) {
                    continue;
                }
                $allowedImageExtensions = ['jpg', 'jpeg', 'gif', 'png'];
                $extension = pathinfo($file)['extension'];
                if (!in_array($extension, $allowedImageExtensions)) {
                    continue;
                }
                $thumbFile = str_replace($dir . '/', $dir . '/thumb.', $file);
                if (!Storage::exists($thumbFile)) {
                    $thumbFile = $file;
                }
                $images[] = [
                    'url' => Storage::url($file),
                    'thumb' =>  Storage::url($thumbFile),
                    'tag' => 'post'
                ];
            }
        }

        return $images;
    }

    /**
     * show the form for uploading a new image
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create()
    {
        return view("blogetc_admin::imageupload.create", []);
    }

    /**
     * Save a new uploaded image
     *
     * @param UploadImageRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     *
     * @throws \Exception
     */
    public function store(UploadImageRequest $request)
    {
        $processed_images = $this->processUploadedImages($request);

        return view("blogetc_admin::imageupload.uploaded", [
            'images' => $processed_images
        ]);
    }

    /**
     * Save a new uploaded by froala editor image.
     *
     * @param Request $request
     * @return array
     *
     * @throws \Exception
     */
    public function storeFloara(Request $request)
    {
        // save uploaded image to storage.
        $dir = config("blogetc.blog_upload_dir");
        $file = $request->file('file');

        $path = Storage::put($dir, $file);

        // make image thumbnail in storage.
        $image = \Image::make($file->getRealPath());
        $image->resize(200, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        $fileName = pathinfo($path, PATHINFO_FILENAME);
        $thumbPath = str_replace($fileName, 'thumb.' . $fileName, $path);
        $stream = $image->stream(
            pathinfo($path, PATHINFO_EXTENSION),
            config("blogetc.image_quality", 80)
        );

        Storage::put($thumbPath, $stream);

        return ['link' => Storage::url($path)];
    }

    /**
     * Delete uploaded featured image.
     *
     * @param Request $request
     * @return string
     *
     * @throws \Exception
     */
    public function delete($postId, $imageSize)
    {
        $post = BlogEtcPost::findOrFail($postId);
        $deleted = $post->unsetImage($imageSize);

        $image = BlogEtcUploadedPhoto::where(['blog_etc_post_id' => $postId])->first();
        if ($image) {
            $image->delete();
        }

        return $deleted ? 'ok' : 'not ok';
    }

    /**
     * Delete uploaded by froala editor image.
     *
     * @param Request $request
     * @return string
     *
     * @throws \Exception
     */
    public function deleteFloara(Request $request)
    {
        $deleted = false;

        $src = $request->get('src');
        if (!empty($src)) {
            $src = urldecode($src);
            $src = str_replace('/files', '', $src);
            if (Storage::exists($src)) {
                $deleted = Storage::delete($src);
                $originalPath = str_replace('thumb.', '', $src);
                if (Storage::exists($originalPath)) {
                    $deleted = Storage::delete($originalPath);
                }
            }
        }

        return $deleted ? 'ok' : 'not ok';
    }

    /**
     * Process any uploaded images (for featured image)
     * @todo - This class was added after the other main features, so this duplicates some code from the main blog post admin controller (BlogEtcAdminController). For next full release this should be tided up.
     *
     * @param UploadImageRequest $request
     * @return array returns an array of details about each file resized.
     *
     * @throws \Exception
     */
    protected function processUploadedImages(UploadImageRequest $request)
    {
        $this->increaseMemoryLimit();
        $photo = $request->file('upload');
        // to save in db later
        $uploaded_image_details = [];
        $sizes_to_upload = $request->get("sizes_to_upload");

        // now upload a full size - this is a special case, not in the config file. We only store full size images in this class, not as part of the featured blog image uploads.
        if (isset($sizes_to_upload['blogetc_full_size']) && $sizes_to_upload['blogetc_full_size'] === 'true') {
            $uploaded_image_details['blogetc_full_size'] = $this->UploadAndResize(null, $request->get("image_title"), 'fullsize', $photo);
        }

        foreach ((array)config('blogetc.image_sizes') as $size => $image_size_details) {
            if (!isset($sizes_to_upload[$size]) || !$sizes_to_upload[$size] || !$image_size_details['enabled']) {
                continue;
            }
            // this image size is enabled, and
            // we have an uploaded image that we can use
            $uploaded_image_details[$size] = $this->UploadAndResize(null, $request->get("image_title"), $image_size_details, $photo);
        }

        // store the image upload.
        BlogEtcUploadedPhoto::create([
            'image_title' => $request->get("image_title"),
            'source' => "ImageUpload",
            'uploader_id' => optional(\Auth::user())->id,
            'uploaded_images' => $uploaded_image_details,
        ]);

        return $uploaded_image_details;
    }
}
