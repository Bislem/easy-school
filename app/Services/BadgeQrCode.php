<?php
namespace App\Services;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
class BadgeQrCode
{
    public function svg(string $url, int $size=180): string
    {
        $matrix=Encoder::encode($url,ErrorCorrectionLevel::M())->getMatrix();
        $margin=4; $dimension=$matrix->getWidth()+($margin*2); $module=$size/$dimension; $rects='';
        foreach($matrix->getArray()->toArray() as $y=>$row) foreach($row as $x=>$dark) if($dark)$rects.='<rect x="'.(($x+$margin)*$module).'" y="'.(($y+$margin)*$module).'" width="'.$module.'" height="'.$module.'"/>';
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'"><rect width="100%" height="100%" fill="#fff"/><g fill="#111">'.$rects.'</g></svg>';
    }
}
