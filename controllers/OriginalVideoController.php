<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\OriginalVideo;
use App\User;
use App\Http\Resources\OriginalVideo as OriginalVideoResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use FFMpeg;

class OriginalVideoController extends Controller
{
    public $ffmpeg_path;
    public $ffmprope_path;

    public function __construct()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->ffmpeg_path = 'C:/FFMpeg/bin/ffmpeg.exe';
            $this->ffmprope_path = 'C:/FFMpeg/bin/ffprobe.exe';
        } else {
            $this->ffmpeg_path = '/usr/local/bin/ffmpeg';
            $this->ffmprope_path = '/usr/local/bin/ffprobe';
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $token = $request->token;
        $user = User::where('api_token', $token)->firstOrFail();
        $video = OriginalVideo::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();
        return OriginalVideoResource::collection($video);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $token = $request->token;
        $user = User::where('api_token', $token)->firstOrFail();

        // Check file size
        $file = $request->file('file');
        Log::info('uploaded file size: '.$file->getClientSize());
        Log::info('max file size: '.$file->getMaxFilesize());
        if ($file->getClientSize() > $file->getMaxFilesize()
            || $file->getClientSize() === 0) {
            return response('File size too large. Max file size is 2 MB.', 400);
        }

        // Check mp4 file
        $validator = Validator::make($request->all(), [
                'file'  => 'required|mimes:mp4|max:2000',
            ]
        );

        if ($validator->fails()) {
            $errors = '';
            foreach ($validator->errors()->get('file') as $error) {
                $errors .= $error . '<br>';
            }
            return response()->json($errors, 400);
        }

        $ffprobe = \FFMpeg\FFProbe::create([
            'ffmpeg.binaries'  => $this->ffmpeg_path,
            'ffprobe.binaries' => $this->ffmprope_path,
        ]);

        try {
            $info = $ffprobe
            ->streams($request->file)
            ->videos()
            ->first();
            //Log::info(var_export($info, true)); // show all info
        } catch (\Exception $e) {
            return response()->json('Please try again. Make sure you are uploading a valid file.', 400);
        }

        $frame_rate = $info->get('r_frame_rate');
        // Need to eval frame rate ('30000/1001') to get float
        $fps = eval("return $frame_rate;");

        // Store meta data of the video
        $video = new OriginalVideo;
        $video->name = $request->name;
        $video->fps = $fps;
        $video->num_frames = $info->get('nb_frames');
        $video->width = $info->get('width');
        $video->height = $info->get('height');
        $video->user_id = $user->id;

        if ($video->save()) {
            $path = $request
                ->file('file')
                ->storeAs('public/original_videos', $video->id.'.mp4');
            //TODO choose a better frame rate
            $this->extractImages($request->file, $video->id, 20);
            $this->processImages($video->id);
            $this->createVideo($video->id, 20);
            return new OriginalVideoResource($video);
        } else {
            Log::warning('failed to save video');
        }
        return response('Failed to process video.', 400);
    }

    private function extractImages($file, $id, $frame_rate)
    {
        // $ffmpeg = \FFMpeg\FFMpeg::create([
        //     'ffmpeg.binaries'  => $this->ffmpeg_path,
        //     'ffprobe.binaries' => $this->ffmprope_path,
        // ]);
        // $ffmpeg_video = $ffmpeg->open($file);

        $video_path = "storage/original_videos/$id.mp4";
        $path = "storage/original_images/$id";
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        // // TODO wait for PHP-FFMpeg to fix the bug which produces duplicate files
        // $ffmpeg_video
        //     ->filters()
        //     ->extractMultipleFrames($frame_rate, $path)
        //     ->synchronize();
        // $ffmpeg_video
        //     ->save(new FFMpeg\Format\Video\X264(), "$path/output_%d.png");

        exec("ffmpeg -i $video_path -vf fps=$frame_rate $path/%05d.png");
    }

    private function processImages($id) {
        $path = "storage/processed_images/$id";
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        $cwd = getcwd();
        chdir("$cwd/../face_detection_win/cmake-build-release");
        exec("face_detection.exe ../../storage/app/public/original_images/$id ../../storage/app/public/processed_images/$id $id");
        chdir($cwd);
    }

    private function createVideo($id, $frame_rate) {
        $images_path = "storage/processed_images/$id";
        $path = "storage/processed_videos";
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        exec("ffmpeg -r $frame_rate -i $images_path/$id.%d.png -c:v libx264 -vf \"fps=$frame_rate,format=yuv420p\" $path/$id.mp4");
    }

    /**
     * Display the specified resource.
     *
     * @param  int
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $video = OriginalVideo::findOrFail($id);
        return view('video.single', compact('video'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $video = OriginalVideo::findOrFail($id);

        if ($video->delete()) {
          Storage::disk('local')->delete('public/original_videos/'.$id.'.mp4');
          Storage::disk('local')->delete('public/processed_videos/'.$id.'.mp4');
          Storage::deleteDirectory('public/original_images/'.$id);
          Storage::deleteDirectory('public/processed_images/'.$id);
          return response('Video removed successfully.', 200);
        }
        return response('Failed to remove video', 400);
    }

    /**
     * Delete the specified video from a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {
        $user = User::where('api_token', $request->token)->firstOrFail();
        $id = $request->id;
        $video = OriginalVideo::where('user_id', $user->id)->findOrFail($id);
        return OriginalVideoController::destroy($id);
    }
}
