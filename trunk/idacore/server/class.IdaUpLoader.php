<?php
/**
 * Upload stuff
 * @package ida
 */

/**
 * Class for file uploads
 * @package ida
 */
Class IdaUpLoader {

    function IdaUpLoader() {
    
        $this->path = $_SERVER['DOCUMENT_ROOT']._APP_DATA_PATH;
        if(!is_writable($this->path."files")) {
            throw new Exception($this->path.'files is is not writable!');
        }
    }

    function upLoad() {

        if (!is_uploaded_file($_FILES['uploadfile']['tmp_name']) || $_FILES['uploadfile']['tmp_name'] == '') {

            throw new Exception('No file. Maybe file was too big?');

        } else {
            $pathinfo = $this->pathinfo_im($_FILES['uploadfile']['name']);
            $pathinfo['extension'] = strtolower($pathinfo['extension']);
            $extension = $pathinfo['extension'];

            $f = $_FILES['uploadfile']['tmp_name'];
            $filetype = exec("file -b $f");

            switch ($filetype) {
            case strpos($filetype,'image') !== false:
                return $this->uploadImageFile($pathinfo);
                break;

            case strpos($filetype,'PC bitmap data') !== false:
                return $this->uploadImageFile($pathinfo);
                break;

            case strpos($filetype,'XML') !== false:
                if($extension == 'svg') 
                    $this->uploadImageFile($pathinfo);
                else if($extension == 'xml')
                    $this->uploadTextFile($pathinfo);
                else if($extension == 'smil')
                    $this->uploadSmilFile($pathinfo);
                    
                break;
/*
            case strpos($filetype,'script') !== false: // no scripts
                throw new Exception('File type '.$extension.' is not supported!');
                break;

            case strpos($filetype,'text') !== false:
                if($extension == 'txt') {
                    $this->uploadTextFile($pathinfo);
                    break;
                }
                

            case strpos($filetype,'Zip archive data') !== false:    // OpenOffice files are zip-files
                if($extension == 'sxw' || $extension == 'odt'){
                    $this->uploadTextFile($pathinfo);
                    break;
                }

            case strpos($filetype,'gzip compressed data') !== false:
                    $this->uploadZipFile($pathinfo);
                break;

            case strpos($filetype,'PDF document') !== false:
                $this->uploadTextFile($pathinfo);
                break;

            case strpos($filetype,'Blender3D') !== false:
                $this->uploadBlenderFile($pathinfo);
                break;

            case strpos($filetype,'Vorbis audio') !== false:
                $this->uploadSoundFile($pathinfo);
                break;

            case strpos($filetype,'MP3 file') !== false:
                $this->uploadSoundFile($pathinfo);
                break;
*/
            default:
                throw new Exception('File type '.$extension.' is not supported!');
                break;
            }
        }
    }

    function uploadTextFile($pathinfo) {
        throw new Exception('File type is not implemented yet!');
    }


    function uploadSmilFile($pathinfo) {
        $new_basename = $this->getNewFileName();
        $new_filename = $new_basename.'.'.$pathinfo['extension'];

        // extension
        $extension = $pathinfo['extension'];
        $this->uploadFile($this->path.'/files/', $new_filename);

        // set values
        $values = array();
        $values['original_filename'] = $pathinfo['basename'];
        $values['filename'] = $new_basename;
        $values['extension'] = $extension;

        IdaDB::insert('file', $values);


    }


    function uploadZipFile($pathinfo) {
        throw new Exception('File type is not implemented yet!');
    }




    function uploadBlenderFile($pathinfo) {
        throw new Exception('File type is not implemented yet!');
    }




    function uploadSoundFile($pathinfo) {
        throw new Exception('File type is not implemented yet!');
    }


    function importImage($filename) {

        $import = _IMPORT_DIRECTORY.$filename;
        $path = $_SERVER['DOCUMENT_ROOT']._APP_DATA_PATH;
        $newName = IdaUpLoader::getNewFileName();
        $pathinfo = IdaUploader::pathinfo_im($import);

        // image extension
        if($pathinfo['extension'] == 'jpg' || $pathinfo['extension'] == 'jpeg') {
            $extension = 'jpg';
        } else {
            $extension = $pathinfo['extension'];
        }

        if(file_exists($import)) {

            $exifDate = IdaUploader::readExif($import);
            // move file from import to files
           if( rename($import, $path."/files/".$newName.".".$pathinfo["extension"])) {

                $img = $path.'files/'.$newName.'.'.$pathinfo["extension"];
                $jpg_file =  $path.'images/'.$newName.'.jpg';
                exec("convert $img $jpg_file", $retval);
                if (($retval)) 
                    throw new Exception("Conversion to jpg failed!");

                $thumb =  $path.'/thumbnails/'.$newName.'.jpg';        // thumbnails are always jpg-files
                $make_magick = exec("convert -thumbnail "._THUMBNAIL_WIDTH." x  $jpg_file $thumb", $retval);

                $size = getimagesize($img);
                 // set values
                $values = array();
                $values['original_filename'] = $pathinfo['basename'];
                $values['width'] = $size[0];
                $values['height'] = $size[1];
                $values['fname'] = $newName;
                $values['extension'] = $pathinfo["extension"];
                $values['exif_date'] = $exifDate;

                IdaDB::insert('file', $values);

       

            } else {
                throw new Exception("Could not move the file!");
            }
        } else {

                throw new Exception("File not found!");
        }

    }

    function uploadImageFile($pathinfo) {


        $new_basename = $this->getNewFileName();
        $new_filename = $new_basename.'.'.$pathinfo['extension'];
        $bigfile = $new_filename;

        // image extension
        if($pathinfo['extension'] == 'jpg' || $pathinfo['extension'] == 'jpeg') {
            $extension = 'jpg';
        } else {
            $extension = $pathinfo['extension'];
        }


        $this->uploadFile($this->path.'/files/', $bigfile);
        $img = $this->path.'/files/'.$bigfile;
        // chmod($img, 0644);

       $exifDate = $this->readExif($bigfile);
        

        //TODO: Check if imagemagick is installed!!!

        $size = getimagesize($img);
        $jpg_file =  $this->path.'/images/'.$new_basename.'.jpg';
        $make_magick = exec("convert $img $jpg_file", $retval);
        if (($retval)) echo 'Error: Please try again.';


        $thumb =  $this->path.'/thumbnails/'.$new_basename.'.jpg';        // thumbnails are always jpg-files
//        $minithumb =  $this->path.'/minithumbnails/'.$new_basename.'.jpg';

        // make thumbnails
        $make_magick = exec("convert -thumbnail "._THUMBNAIL_WIDTH." x  $jpg_file $thumb", $retval);
        if (($retval)) echo 'Error: Please try again.';
  //      $make_magick = exec("convert -thumbnail "._MINITHUMBNAIL_WIDTH." x  $thumb $minithumb", $retval);
  //      if (($retval)) echo 'Error: Please try again.';


        $tsize = getimagesize($thumb);
/*
        $target = new IdaRecord("Digital_Image");

        $dom = new DomDocument('1.0', 'UTF-8');
        $rootNode = $dom->createElement('P1F.is_identified_by');
        $nameNode = $dom->createElement('name');
        $nameNode->nodeValue = "koe";
        $rootNode->append($nameNode);
        $target->parseDataFromXML(&$rootNode);

        if(!$target->hasErrors()) {

            $target->save();

        }
*/
        // set values
        $values = array();
        $values['original_filename'] = $pathinfo['basename'];
        $values['width'] = $size[0];
        $values['height'] = $size[1];
        $values['thumb_width'] = $tsize[0];
        $values['thumb_height'] = $tsize[1];
        $values['fname'] = $new_basename;
        $values['extension'] = $extension;
        $values['exif_date'] = $exifDate;

        IdaDB::insert('file', $values);

        return $new_basename;	

    }



    function uploadFile($path, $new_filename) {
        
        if(is_writable($path)) {
            if (move_uploaded_file($_FILES['uploadfile']['tmp_name'], $path.$new_filename)) {

            } else {
                echo 'error in upload';
                die($_FILES['uploadfile']['error']);
            }
        } else {
            die($path);
        }
    }





    function getNewFileName() {
        return date('Y_m_d_H_i_s').mt_rand().'_u'.$_SESSION['uid'];
    }

    // pathinfo improved
    function pathinfo_im($path) {

        $tab = pathinfo($path);

        $tab["basenameWE"] = substr($tab["basename"],0
        ,strlen($tab["basename"]) - (strlen($tab["extension"]) + 1) );

        return $tab;
    }


    
    static function readExif($file) {
        try {
            $exif = exif_read_data($file);
            if($exif === false) die('error');

            foreach ($exif as $key => $val) {

                switch ($key) {

                case 'DateTimeOriginal' :
                    return $val;
                    break;


                default:
                    break;
                }
            }
        
            
        } catch(Exception $e) {
            return "0";
        }
    }
}






?>
