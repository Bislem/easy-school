<?php
namespace App\Enums;
enum CertificateType:string {case ENROLLMENT='enrollment_attestation';case TRAINING='training_attestation';case ATTENDANCE='attendance_attestation';case SUCCESS='success_certificate';case DIPLOMA='diploma';public function label():string{return match($this){self::ENROLLMENT=>"Attestation d'inscription",self::TRAINING=>'Attestation de formation',self::ATTENDANCE=>'Attestation de présence',self::SUCCESS=>'Certificat de réussite',self::DIPLOMA=>'Diplôme / certificat'};}}
