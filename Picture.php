<?php


class picture {

    var $smallwidth = 160;
    var $smallheight = 120;
    var $bigwidth = 800;
    var $bigheight = 600;
    var $original = true;
    var $err = false;

    function picture($ERPhost,$ERPuser,$ERPpass,$ERPimgdir,$SHOPhost,$SHOPuser,$SHOPpass,$SHOPimgdir,$err) {
        $this->ERPftphost = $ERPhost;
        $this->ERPftpuser = $ERPuser;
        $this->ERPftppwd = $ERPpass;
        $this->ERPimgdir = $ERPimgdir;
        $this->SHOPftphost = $SHOPhost;
        $this->SHOPftpuser = $SHOPuser;
        $this->SHOPftppwd = $SHOPpass;
        $this->SHOPimgdir = $SHOPimgdir;
        $this->err = $err;
        $this->mkLeinwand();
        //if ( !class_exists("Imagick") ) { $this->err->out("Imagick-Extention nicht installiert",true); return false; };
    }

    function mkLeinwand() {
        $img    = new Imagick();
        $img->newImage($this->smallwidth,$this->smallheight,new ImagickPixel('white'));
        $img->setImageFormat('png');
        $rc = $img->writeImage( "/tmp/tmp.img_small.png");
        $img->newImage($this->bigwidth,$this->bigheight,new ImagickPixel('white'));
        $img->setImageFormat('png');
        $rc = $img->writeImage( "/tmp/tmp.img_big.png");
        $img->clear(); 
        $img->destroy();
    }

    function copyImage($id,$image,$typ) {
        if ( !$this->fromERP($image) ) return false;
        if ( !$this->mkbilder($typ) ) return false;
        return $this->toShop($id,$typ);
    }

    function mkbilder($typ) {
        $org = new Imagick();
        if ( !$org->readImage("/tmp/tmp.file_org") ) return false;
        $big = new Imagick("/tmp/tmp.img_big.png");
        $big->setImageColorspace($org->getImageColorspace() );
        $big->setImageType(imagick::IMGTYPE_TRUECOLOR);
        $org->scaleImage($this->bigwidth,$this->bigheight,true);
        $d = $org->getImageGeometry();
        $xoff = ($this->bigwidth-$d['width'])/2;
        $big->compositeImage($org,imagick::ALIGN_CENTER,$xoff,0);
        $rc = $big->writeImage( "/tmp/tmp.file_org");
        $big->clear(); $big->destroy();

        $small = new Imagick("/tmp/tmp.img_small.png");
        $small->setImageColorspace($org->getImageColorspace() );
        $small->setImageType(imagick::IMGTYPE_TRUECOLOR);
        $org->scaleImage($this->smallwidth,$this->smallheight,true);
        $d = $org->getImageGeometry();
        $xoff = ($this->smallwidth-$d['width'])/2;
        $small->compositeImage($org,imagick::ALIGN_CENTER,$xoff,0);
        $rc = $small->writeImage( "/tmp/tmp.file_small");
        $small->clear(); $small->destroy();
        $org->clear(); $org->destroy();
        return true;
    }
    function _mkbilder($typ) {
        $handle = new Imagick();
        $img    = new Imagick();
        if ( !$handle->readImage("/tmp/tmp.file_org") ) return false;
        $d = $handle->getImageGeometry();
        if ( $d["width"]<$d["height"] ) {
            $h=true;
            $faktor = 1/($d["height"]/$d["width"]);
        } else {
            $h=false;
            $faktor = $d["width"]/$d["height"];
        }

        $img->newImage($this->smallwidth,$this->smallheight,new ImagickPixel('white'));
        $img->setImageFormat($typ);
        $smallheight = floor($this->smallwidth*$faktor);
        $handle->thumbnailImage($this->smallwidth, $smallheight, true);
        $img->setImageColorspace($handle->getImageColorspace() );
        $img->compositeImage($handle,imagick::GRAVITY_CENTER,0,0);
        $img->compositeImage($handle,$handle->getImageCompose(),0,0);
        $handle->clear(); $handle->destroy();
        $rc = $img->writeImage( "/tmp/tmp.file_small");
        $img->clear(); $img->destroy();

        if ( !$this->original ) {
            $handle = new Imagick();
            $img->newImage($this->bigwidth,$this->bigheight,new ImagickPixel('white'));
            $img->setImageFormat($typ);
            $handle->readImage("/tmp/tmp.file_org");
            $bigheight = floor($this->bigwidth * $faktor);
            $handle->thumbnailImage( $this->bigwidth, $bigheight,true);
            $img->compositeImage($handle,imagick::GRAVITY_CENTER,0,0);
            $handle->clear(); $handle->destroy();
            return $img->writeImage( "/tmp/tmp.file_org");
            $img->clear(); $img->destroy();
        }
        return $rc;
    }

    function fromERP($image) {
        if ( $this->ERPftphost == 'localhost' ) {
            exec("cp $this->ERPimgdir/$image /tmp/tmp.file_org",$aus,$rc2);
            if ( $rc2>0 ) { $this->err->out("[Downloadfehler: $image]",true); return false; };
        } else {
            $conn_id = ftp_connect($this->ERPftphost);
            $rc = @ftp_login($conn_id,$this->ERPftpuser,$this->ERPftppwd);
            $src = $this->ERPimgdir."/".$image;
            $upload = @ftp_get($conn_id,"/tmp/tmp.file_org","$src",FTP_BINARY);
            if ( !$upload ) { $this->err->out("[Ftp Downloadfehler! $image]",true); return false; };
            ftp_quit($conn_id);
        }
        $this->image = $image;
        return true;
    }

    function toShop($id,$typ) {
        $grpic = $id."_gr.".$typ;
        $klpic = $id."_kl.".$typ;
        if ( $this->SHOPftphost == 'localhost' ) {
            exec("cp /tmp/tmp.file_org $this->SHOPimgdir/$grpic",$aus,$rc1);
            exec("cp /tmp/tmp.file_small $this->SHOPimgdir/$klpic",$aus,$rc2);
            if ( $rc1>0 || $rc2>0 ) { $this->err->out("[Uploadfehler: $this->image / $grpic]",true); return false; };
        } else {
            $conn_id = ftp_connect($this->SHOPftphost);
            @ftp_login($conn_id,$this->SHOPftpuser,$this->SHOPftppwd);
            @ftp_chdir($conn_id,$this->SHOPimgdir);
            $upload = @ftp_put($conn_id,$this->SHOPimgdir."/$grpic","/tmp/tmp.file_org",FTP_BINARY);
            if ( !$upload ) { $this->err->out("[Ftp Uploadfehler! $grpic]",true); return false; };
            $upload = @ftp_put($conn_id,$this->SHOPimgdir."/$klpic","/tmp/tmp.file_small",FTP_BINARY);
            if ( !$upload ) { $this->err->out("[Ftp Uploadfehler! $klpic]",true); return false; };
            @ftp_quit($conn_id);
        }
        return true;
    }


}
?>
