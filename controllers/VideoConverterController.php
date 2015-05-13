<?php

namespace Plugins\videoconverter\controllers;

require_once plugins_path() . '/videoconverter/vendor/autoload.php';

class VideoConverterController extends \BackendbaseController
{

    /**
     * @var string
     */
    protected $layout = 'layouts.backend.laradmin';


    public function manage()
    {

        $videos = \Video::paginate(get_config_value('paging'));

        $this->layout->content = \View::make('videoconverter::manage')->withVideos($videos);
    }

    private function texttopng($string){
        if($string!='watermark'){
            $font = 15;
            $im = @imagecreatetruecolor(strlen($string) * $font / 1.1, $font+20);
            imagesavealpha($im, true);
            imagealphablending($im, false);
            $white = imagecolorallocatealpha($im, 255, 255, 255, 127);
            imagefill($im, 0, 0, $white);
            $lime = imagecolorallocate($im, 204, 255, 51);
            imagettftext($im, $font, 0, 0, $font +10, $lime, plugins_path().'/videoconverter/vendor/fonts/Tahoma.ttf', $string);


            imagepng($im,plugins_path().'/videoconverter/watermark.png');
            imagedestroy($im);
        }
        return plugins_path().'/videoconverter/watermark.png';
    }


    public function frames($video_id,$frames){
        $video = \Video::find($video_id);
        if (is_null($video) || !trim($frames)) {
            return \Redirect::back()->withMessage($this->notifyView(t('videoconverter::strings.video_not_found'), 'danger'));
        }
        $error = false;
        $frames = explode(",",$frames);
        $filepath = str_replace(asset_url('videos'), asset_path('videos'), $video->path);
        $ffmpeg = \FFMpeg\FFMpeg::create();
        $newvideo = $ffmpeg->open($filepath);
        foreach($frames as $frame){
            $name = $video->filename . ".frame.{$frame}.jpg";
            try{
                $newvideo
                    ->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds($frame))
                    ->save(asset_path('images').'/'.$name);
            }catch (\Exception $e) { $error = $e->getMessage(); }
            if(!$error){
                if(!$this->saveimagetoassets(array(
                    'name'      =>  $name,
                    'mime'      =>  'image/jpeg',
                    'size'      =>  0,
                    'path'      =>  'images/'.$name
                ))){
                    $error = t('videoconverter::strings.cant_save_image');
                }
            }
        }

        if(!$error){
            return \Redirect::back()->withMessage($this->notifyView(t('videoconverter::strings.frames_extracted')));
        }
        return \Redirect::back()->withMessage($this->notifyView(t('videoconverter::strings.error_occured').' - '.$error,'danger'));
    }

    public function watermark($video_id, $watermark){
        if(!trim($watermark)){
            $watermark = get_config_value('brand');
        }
        $video = \Video::find($video_id);
        if (is_null($video)) {
            return \Redirect::back()->withMessage($this->notifyView(t('videoconverter::strings.video_not_found'), 'danger'));
        }
        $error = false;

        $filepath = str_replace(asset_url('videos'), asset_path('videos'), $video->path);


        $ffmpeg = \FFMpeg\FFMpeg::create();
        $newvideo = $ffmpeg->open($filepath);
        $name = $video->filename . ".".time().".webm";
        try{
            $newvideo->filters()->watermark($this->texttopng($watermark),array('bottom'=>100));

            $newvideo->save(new \FFMpeg\Format\Video\WebM(), asset_path('videos') . '/' . $name);
        }catch (\Exception $e){ $error = $e->getMessage(); }


        $type = "video/webm";

        if(!$error){
            if($this->savevideotoassets(array(
                    'name'      =>  $name,
                    'mime'      =>  $type,
                    'size'      =>  0,
                    'path'      =>  'videos/'.$name
                ))){
                return \Redirect::back()->withMessage($this->notifyView(t('videoconverter::strings.video_watermarked')));
            }
        }

        return \Redirect::back()->withMessage($this->notifyView(t('videoconverter::strings.error_occured').' - '.$error,'danger'));

    }

    public function resize($video_id, $newsize){
        $video = \Video::find($video_id);
        if (is_null($video)) {
            return \Redirect::back()->withMessage($this->notifyView(t('videoconverter::strings.video_not_found'), 'danger'));
        }
        $error = false;
        $size = explode("x",$newsize);
        $filepath = str_replace(asset_url('videos'), asset_path('videos'), $video->path);


        $ffmpeg = \FFMpeg\FFMpeg::create();
        $newvideo = $ffmpeg->open($filepath);
        try{
            $newvideo->filters()->resize(new \FFMpeg\Coordinate\Dimension($size[0],$size[1]))->synchronize();

            $newvideo->save(new \FFMpeg\Format\Video\WebM(), asset_path('videos') . '/' . $video->filename . ".{$newsize}.webm");
        }catch (\Exception $e){ $error = $e->getMessage(); }

        $name = $video->filename . "{$newsize}.webm";
        $type = "video/webm";

        if(!$error){
            if($this->savevideotoassets(array(
                    'name'      =>  $name,
                    'mime'      =>  $type,
                    'size'      =>  0,
                    'path'      =>  'videos/'.$name
                ))){
                return \Redirect::back()->withMessage($this->notifyView(t('videoconverter::strings.video_resized')));
            }
        }

        return \Redirect::back()->withMessage($this->notifyView(t('videoconverter::strings.error_occured').' - '.$error,'danger'));

    }

    public function convert($video_id, $totype)
    {
        $video = \Video::find($video_id);
        if (is_null($video)) {
            return \Redirect::back()->withMessage($this->notifyView(t('videoconverter::strings.video_not_found'), 'danger'));
        }
        $filepath = str_replace(asset_url('videos'), asset_path('videos'), $video->path);

        $ffmpeg = \FFMpeg\FFMpeg::create();
        $newvideo = $ffmpeg->open($filepath);
        $error = false;

        switch ($totype) {
            case "mpeg":
                try{
                    $newvideo->save(new \FFMpeg\Format\Video\X264(), asset_path('videos') . '/' . $video->filename . ".mpeg.mp4");
                }catch (\Exception $e){ $error = $e->getMessage(); }

                $name = $video->filename . ".mpeg.mp4";
                $type = "video/mpeg";
                break;
            case "mp4":
                try{
                    $newvideo->save(new \FFMpeg\Format\Video\X264(), asset_path('videos') . '/' . $video->filename . ".mp4");
                }catch (\Exception $e){ $error = $e->getMessage(); }

                $name = $video->filename . ".mp4";
                $type = "video/mp4";
                break;
            case "webm":
                try{
                    $newvideo->save(new \FFMpeg\Format\Video\WebM(), asset_path('videos') . '/' . $video->filename . ".webm");
                }catch (\Exception $e){ $error = $e->getMessage(); }
                $name = $video->filename . ".webm";
                $type = "video/webm";
                break;
            case "wmv":
                try{
                    $newvideo->save(new \FFMpeg\Format\Video\WMV(), asset_path('videos') . '/' . $video->filename . ".wmv");
                }catch (\Exception $e){ $error = $e->getMessage(); }
                $name = $video->filename . ".wmv";
                $type = "video/x-ms-wmv";
                break;
            case "ogg":
                try{
                    $newvideo->save(new \FFMpeg\Format\Video\Ogg(), asset_path('videos') . '/' . $video->filename . ".ogv");
                }catch (\Exception $e){ $error = $e->getMessage(); }
                $name = $video->filename . ".ogv";
                $type = "video/ogg";
                break;
        }

        //add the file to the asset manager
        if($this->savevideotoassets(array(
            'name'      =>  $name,
            'mime'      =>  $type,
            'size'      =>  0,
            'path'      =>  'videos/'.$name
        )) && !$error){
            return \Redirect::back()->withMessage($this->notifyView(t('videoconverter::strings.video_converted')));
        }
        return \Redirect::back()->withMessage($this->notifyView(t('videoconverter::strings.error_occured').' - '.$error,'danger'));
    }


    public function savevideotoassets($file){
        $video = new \Video;

        $video->filename = $file['name'];
        $video->type = $file['mime'];

        $path = $file['path'];

        $video->path = files_path().'/'.$path;

        //read file info with id3
        $data = get_fileid3_info(files_path().'/'.$path);
        $video->full_data = $data;
        $video->duration = 0;
        $video->width = 0;
        $video->height = 0;
        $video->filesize = 0;
        if(isset($data['filesize']))
            $video->filesize = $data['filesize'];
        if(isset($data['playtime_seconds']))
            $video->duration = $data['playtime_seconds'];
        if(isset($data['video']) && isset($data['video']['resolution_x']))
            $video->width = $data['video']['resolution_x'];
        if(isset($data['video']) && isset($data['video']['resolution_y']))
            $video->height = $data['video']['resolution_y'];

        if($video->save()){
            return true;
        }
        return false;
    }

    public function saveimagetoassets($file){
        lr($file);
        $image = new \ImageAsset;
        $image->original_filename = $file['name'];
        $image->type = $file['mime'];


        $path = $file['path'];
        $image->path = $path;
        $data=get_fileid3_info(public_path().'/lp-content/files/'.$path);
        $image->filesize = $data['filesize'];
        $image->full_data = $data;
        $image->width = $data['video']['resolution_x'];
        $image->height = $data['video']['resolution_y'];
        $image->ratio = 1.00;
        $image->filename = $file['name'];

        $image->sizes = array(
            'original'=> \Config::get('app.url').'/lp-content/files/'.$path
        );

        if($image->save()){
            return true;
        }
        return false;
    }
}