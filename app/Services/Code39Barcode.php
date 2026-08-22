<?php
namespace App\Services;
class Code39Barcode
{
    private const MAP=['0'=>'nnnwwnwnn','1'=>'wnnwnnnnw','2'=>'nnwwnnnnw','3'=>'wnwwnnnnn','4'=>'nnnwwnnnw','5'=>'wnnwwnnnn','6'=>'nnwwwnnnn','7'=>'nnnwnnwnw','8'=>'wnnwnnwnn','9'=>'nnwwnnwnn','A'=>'wnnnnwnnw','B'=>'nnwnnwnnw','C'=>'wnwnnwnnn','D'=>'nnnnwwnnw','E'=>'wnnnwwnnn','F'=>'nnwnwwnnn','G'=>'nnnnnwwnw','H'=>'wnnnnwwnn','I'=>'nnwnnwwnn','J'=>'nnnnwwwnn','K'=>'wnnnnnnww','L'=>'nnwnnnnww','M'=>'wnwnnnnwn','N'=>'nnnnwnnww','O'=>'wnnnwnnwn','P'=>'nnwnwnnwn','Q'=>'nnnnnnwww','R'=>'wnnnnnwwn','S'=>'nnwnnnwwn','T'=>'nnnnwnwwn','-'=>'nnnnwnwnw','*'=>'nwnnwnwnn'];
    public function svg(string $value,int $height=60): string
    {
        $value='*'.preg_replace('/[^0-9A-T-]/','',strtoupper($value)).'*';$x=10;$bars='';
        foreach(str_split($value) as $char){foreach(str_split(self::MAP[$char]) as $i=>$width){$w=$width==='w'?3:1;if($i%2===0)$bars.='<rect x="'.$x.'" y="2" width="'.$w.'" height="'.($height-16).'"/>';$x+=$w;}$x++;}
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$x.'" height="'.$height.'" viewBox="0 0 '.$x.' '.$height.'"><rect width="100%" height="100%" fill="white"/><g fill="black">'.$bars.'</g><text x="'.($x/2).'" y="'.($height-3).'" text-anchor="middle" font-family="monospace" font-size="9">'.htmlspecialchars(trim($value,'*')).'</text></svg>';
    }
}
