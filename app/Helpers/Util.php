<?php

namespace Muserpol\Helpers;
use DateTime;
use Session;
use Auth;
use DB;
use User;
use Carbon\Carbon;
use Log;
use Muserpol\Models\RetirementFund\RetFunProcedure;
use Muserpol\Models\RetirementFund\RetFunCorrelative;
use Muserpol\Models\Workflow\WorkflowState;
use Muserpol\Models\Role;
use Muserpol\Models\DiscountType;
use Muserpol\Models\RetirementFund\RetirementFund;
use Muserpol\Models\Affiliate;
use Muserpol\Models\Spouse;
use Muserpol\Models\QuotaAidMortuary\QuotaAidCorrelative;
use Muserpol\Models\QuotaAidMortuary\QuotaAidMortuary;
use Muserpol\Models\QuotaAidMortuary\QuotaAidProcedure;
use Muserpol\Models\Contribution\AidContribution;
use Muserpol\Models\Contribution\Contribution;
use Muserpol\Models\Contribution\ContributionProcess;
use Muserpol\Models\Voucher;
class Util
{
    public static function isRegionalRole()
    {
        $wf_state = WorkflowState::where('role_id', self::getRol()->id)->where('sequence_number', 0)->first();
        return $wf_state ? true : false;
    }
    //cambia el formato de la fecha a cadena
    //input $string=YYYY/mm/dd
    public static function getStringDate($string = "1800/01/01", $month_year=false){
        setlocale(LC_TIME, 'es_ES.utf8');        
        $date = DateTime::createFromFormat("Y-m-d", $string);
        if($date){
            if($month_year)
                return strftime("%B de %Y",$date->getTimestamp());
            else
                return strftime("%d de %B de %Y",$date->getTimestamp());
        }
        else 
            return "sin fecha";
    }

    public static function formatMoney($value, $prefix = false)
    {
        if ($value) {
            $value = number_format($value, 2, '.', ',');
            if ($prefix) {
                return 'Bs '.$value;
            }
            return $value;
        }
        return null;
    }
    public static function parseMoney($value)
    {
        $value = str_replace("Bs", "", $value);
        $value = str_replace(",", "", $value);
        return floatval(self::removeSpaces($value));
    }
    public static function parseMonthYearDate($value)
    {
        if (self::verifyMonthYearDate($value) ) {
            return Carbon::createFromFormat('d/m/Y', '01/'.$value)->toDateString();
        }
        return 'invalid Month year';
    }
    public static function parseBarDate($value)
    {
        if (self::verifyBarDate($value) ) {
            return Carbon::createFromFormat('d/m/Y', $value)->toDateString();
        }
        return 'invalid Month year';
    }
    public static function verifyBarDate($value)
    {
        $re = $re = '/^\d{1,2}\/\d{1,2}\/\d{4}$/m';
        preg_match_all($re, $value, $matches, PREG_SET_ORDER, 0);
        return (sizeOf($matches) > 0);
    }
    public static function verifyMonthYearDate($value)
    {
        $re = $re = '/^\d{1,2}\/\d{4}$/m';
        preg_match_all($re, $value, $matches, PREG_SET_ORDER, 0);
        return (sizeOf($matches) > 0);
    }
    public static function formatMonthYear($date)
    {
        setlocale(LC_TIME, 'es_ES.utf8');
        if ($date) {
            if (self::verifyMonthYearDate($date)) {
                $date = Carbon::createFromFormat('d/m/Y', '01/' . $date)->toDateString();
            }
            return Carbon::parse($date)->formatLocalized('%b. %Y');
        }
        return null;
    }
    public static function printMonthYear($date)
    {
        setlocale(LC_TIME, 'es_ES.utf8');
        if ($date) {
            return Carbon::parse($date)->formatLocalized('%b/%Y');
        }
        return null;
    }
    public static function ucw($string)
	{
		if ($string) {
			return ucwords(mb_strtolower($string,'UTF-8'));
		}
    }
    public static function removeSpaces($text)
    {
        $re = '/\s+/';
        $subst = ' ';
        $result = preg_replace($re, $subst, $text);
        return $result ? trim($result) : null;
    }
    public static function fullName($object, $style="uppercase")
    {
        $name = null;
        switch ($style) {
            case 'uppercase':
                $name = mb_strtoupper($object->first_name ?? '') . ' ' . mb_strtoupper($object->second_name ?? '') . ' ' . mb_strtoupper($object->last_name ?? '') . ' ' . mb_strtoupper($object->mothers_last_name ?? '') . ' ' . mb_strtoupper($object->surname_husband ?? '');
                break;
            case 'lowercase':
                $name = mb_strtolower($object->first_name ?? '') . ' ' . mb_strtolower($object->second_name ?? '') . ' ' . mb_strtolower($object->last_name ?? '') . ' ' . mb_strtolower($object->mothers_last_name ?? '') . ' ' . mb_strtolower($object->surname_husband ?? '');
                break;
            case 'capitalize':
                $name = ucfirst(mb_strtolower($object->first_name ?? '')) . ' ' . ucfirst(mb_strtolower($object->second_name ?? '')) . ' ' . ucfirst(mb_strtolower($object->last_name ?? '')) . ' ' . ucfirst(mb_strtolower($object->mothers_last_name ?? '')) . ' ' . ucfirst(mb_strtolower($object->surname_husband ?? ''));
                break;
        }
        $name = self::removeSpaces($name);
        return $name;
    }

    public static function getPDFName($title,$affi){
        $date =  Util::getStringDate(date('Y-m-d'));
        return ($title." - ".$affi->fullName()." - ".$date.".pdf");
    }

    public static function getNextCode($actual, $default = '675' ){
        $year =  date('Y');
        if($actual == "")
            return $default."/".$year;  
        $data = explode('/', $actual);
        if(!isset($data[1]))
            return $default."/".$year;
        return ($year!=$data[1]?"1":($data[0]+1))."/".$year;
    }
    private static function getNextHole($model){
        if(!isset($model[0])) {
            return "";
        }
        $code = explode('/',$model[0]['code']);
        for($i = 1; $i<sizeof($model); $i++)
        {
            $code = explode('/',$model[$i]['code']);
            $last_code = explode('/',$model[$i-1]['code']);
            if($last_code[0]+1 != $code[0] && $last_code[0] != $code[0] && $last_code[1]==$code[1]) {
                return $last_code[0].'/'.$last_code[1];
            }
        }
        return $code[0].'/'.$code[1];
    }
    public static function getNextAreaCode($retirement_fund_id, $save = true){
        $wf_state = WorkflowState::where('module_id',3)->where('role_id', Session::get('rol_id'))->first();
        $reprint = RetFunCorrelative::where('retirement_fund_id',$retirement_fund_id)->where('wf_state_id',$wf_state->id)->first();
        if(isset($reprint->id)){
            Log::info("reprint ret_fun_id: ". $retirement_fund_id);
            return $reprint;
        }
        $year =  date('Y');
        $role = Role::find($wf_state->role_id);

        $year = date('Y');
        $model = RetFunCorrelative::
                            where('wf_state_id',$wf_state->id)
                            ->where('code','NOT LIKE','%A')
                            ->where(DB::raw("split_part(code, '/',2)::integer"),$year)
                            ->orderBy(DB::raw("split_part(code, '/',2)::integer"))
                            ->orderBy(DB::raw("split_part(code, '/',1)::integer"))
                            ->select('code')
                            ->get()
                            ->toArray();

        $hole = self::getNextHole($model);
        $next_correlative='';
        $reception = WorkflowState::where('role_id', Session::get('rol_id'))->whereIn('sequence_number', [0, 1])->first();
        if ($reception) {
            $next_correlative = RetirementFund::find($retirement_fund_id)->code;
        }else{

            if($hole == ""){
                $next_correlative = "1/".$year;
            }
            else{
                $data = explode('/', $hole);
                if(!isset($data[1])){
                    $next_correlative = "1/".$year;
                }else{
                    $next_correlative = ($year!=$data[1]?"1":($data[0]+1))."/".$year;
                    Log::info("correlative created " . $next_correlative);
                }
            }
        }
        if ($save) {
            $role->correlative = $next_correlative;
            $role->save();
        }

        //Correlative 
        $correlative = new RetFunCorrelative();
        $correlative->wf_state_id = $wf_state->id;
        $correlative->retirement_fund_id = $retirement_fund_id;
        $correlative->code = $next_correlative;
        $correlative->date = self::saveDay(Carbon::now()->toDateString());
        $correlative->user_id = self::getAuthUser()->id;

        if ($save) {
            $correlative->save();
        }

        return $correlative;
    }
    public static function getNextAreaCodeQuotaAid($quota_aid_mortuary_id, $save = true){
        $wf_state = WorkflowState::where('module_id',4)->where('role_id', Session::get('rol_id'))->first();
        $quota_aid = QuotaAidMortuary::find($quota_aid_mortuary_id);
        Log::info("role_id: ". Session::get('rol_id'));
        $reprint = QuotaAidCorrelative::where('procedure_type_id',$quota_aid->procedure_modality->procedure_type_id)->where('quota_aid_mortuary_id',$quota_aid_mortuary_id)->where('wf_state_id',$wf_state->id)->first();

        // $last_quota_aid = QuotaAidCorrelative::
        //                         where('procedure_type_id',$quota_aid->procedure_modality->procedure_type_id)
        //                         ->where('wf_state_id',$wf_state->id)
        //                         ->orderByDesc(DB::raw("split_part(code, '/',2)::integer"))
        //                         ->orderByDesc(DB::raw("split_part(code, '/',1)::integer"))
        //                         ->first();
        if(isset($reprint->id))
            return $reprint;
        $year =  date('Y');
        $model = QuotaAidCorrelative::
                            where('procedure_type_id',$quota_aid->procedure_modality->procedure_type_id)
                            ->where('wf_state_id',$wf_state->id)
                            ->where('code','NOT LIKE','%A')
                            ->where(DB::raw("split_part(code, '/',2)::integer"),$year)
                            ->orderBy(DB::raw("split_part(code, '/',2)::integer"))
                            ->orderBy(DB::raw("split_part(code, '/',1)::integer"))
                            ->select('code')
                            ->get()
                            ->toArray();

        $hole = self::getNextHole($model);

        //$correlative = $last_quota_aid->code ?? "";
        $correlative = $hole;
        Log::info("type: ". $quota_aid->procedure_modality->procedure_type_id);
        $reception = WorkflowState::where('role_id', Session::get('rol_id'))->whereIn('sequence_number', [0, 1])->first();
        if ($reception) {
            $correlative = QuotaAidMortuary::find($quota_aid_mortuary_id)->code;
        } else {
            if($correlative == ""){
                $correlative = "1/".$year;
            }
            else{
                $data = explode('/', $correlative);
                if(!isset($data[1]))
                    $correlative = "1/".$year;
                else
                    $correlative = ($year!=$data[1]?"1":($data[0]+1))."/".$year;
            }
        }

        //Correlative
        $quota_aid_correlative = new QuotaAidCorrelative();
        $quota_aid_correlative->wf_state_id = $wf_state->id;
        $quota_aid_correlative->quota_aid_mortuary_id = $quota_aid_mortuary_id;
        $quota_aid_correlative->code = $correlative;
        $quota_aid_correlative->date = Carbon::now();
        $quota_aid_correlative->user_id = self::getAuthUser()->id;
        $quota_aid_correlative->procedure_type_id = $quota_aid->procedure_modality->procedure_type_id;
        if ($save) {
            $quota_aid_correlative->save();
        }

        return $quota_aid_correlative;
    }
    private static $UNIDADES = [
        '',
        'UN ',
        'DOS ',
        'TRES ',
        'CUATRO ',
        'CINCO ',
        'SEIS ',
        'SIETE ',
        'OCHO ',
        'NUEVE ',
        'DIEZ ',
        'ONCE ',
        'DOCE ',
        'TRECE ',
        'CATORCE ',
        'QUINCE ',
        'DIECISEIS ',
        'DIECISIETE ',
        'DIECIOCHO ',
        'DIECINUEVE ',
        'VEINTE '
    ];
    private static $DECENAS = [
        'VEINTI',
        'TREINTA ',
        'CUARENTA ',
        'CINCUENTA ',
        'SESENTA ',
        'SETENTA ',
        'OCHENTA ',
        'NOVENTA ',
        'CIEN '
    ];
    private static $CENTENAS = [
        'CIENTO ',
        'DOSCIENTOS ',
        'TRESCIENTOS ',
        'CUATROCIENTOS ',
        'QUINIENTOS ',
        'SEISCIENTOS ',
        'SETECIENTOS ',
        'OCHOCIENTOS ',
        'NOVECIENTOS '
    ];
    public static function convertir($number, $moneda = '', $centimos = '')
    {
        $number = number_format((float)$number, 2, '.', '');
        $converted = '';
        $decimales = '';
        if (($number < 0) || ($number > 999999999)) {
            return 'No es posible convertir el numero a letras';
        }
        $div_decimales = explode('.',$number);
        if(count($div_decimales) > 1){
            $number = $div_decimales[0];
            $decNumberStr = (string) $div_decimales[1];
            if(strlen($decNumberStr) == 2){
                $decNumberStrFill = str_pad($decNumberStr, 9, '0', STR_PAD_LEFT);
                $decCientos = substr($decNumberStrFill, 6);
                $decimales = self::convertGroup($decCientos);
            }
        }
        $numberStr = (string) $number;
        $numberStrFill = str_pad($numberStr, 9, '0', STR_PAD_LEFT);
        $millones = substr($numberStrFill, 0, 3);
        $miles = substr($numberStrFill, 3, 3);
        $cientos = substr($numberStrFill, 6);
        if (intval($millones) > 0) {
            if ($millones == '001') {
                $converted .= 'UN MILLON ';
            } else if (intval($millones) > 0) {
                $converted .= sprintf('%sMILLONES ', self::convertGroup($millones));
            }
        }
        if (intval($miles) > 0) {
            if ($miles == '001') {
                $converted .= 'UN MIL ';
            } else if (intval($miles) > 0) {
                $converted .= sprintf('%sMIL ', self::convertGroup($miles));
            }
        }
        if (intval($cientos) > 0) {
            if ($cientos == '001') {
                $converted .= 'UN ';
            } else if (intval($cientos) > 0) {
                $converted .= sprintf('%s ', self::convertGroup($cientos));
            }
        }
        if(empty($decimales)){
            // $valor_convertido = $converted . strtoupper($moneda);
            $valor_convertido = $converted . '00/100';
        } else {
            $valor_convertido = $converted . strtoupper($moneda)  . ($div_decimales[1]) . '/100';
            // $valor_convertido = $converted . strtoupper($moneda) . ' CON ' . $decimales . ' ' . strtoupper($centimos);
        }
        return $valor_convertido;
    }
    private static function convertGroup($n)
    {
        $output = '';
        if ($n == '100') {
            $output = "CIEN ";
        } else if ($n[0] !== '0') {
            $output = self::$CENTENAS[$n[0] - 1];
        }
        $k = intval(substr($n,1));
        if ($k <= 20) {
            $output .= self::$UNIDADES[$k];
        } else {
            if(($k > 30) && ($n[2] !== '0')) {
                $output .= sprintf('%sY %s', self::$DECENAS[intval($n[1]) - 2], self::$UNIDADES[intval($n[2])]);
            } else {
                $output .= sprintf('%s%s', self::$DECENAS[intval($n[1]) - 2], self::$UNIDADES[intval($n[2])]);
            }
        }
        return $output;
    }
    
    public static function getAuthUser()
    {
        return Auth::user();
    }

    public static function getRol()
    {
        // return "hola ";
        $roles = Auth::user()->roles;

        $rol = Session::get('rol_id');

        $rol_object =null;
        foreach ($roles as $r) {
            # code...
            if($rol== $r->id)
            {
                $rol_object =$r;
            }
        }

        return $rol_object;

    }
    public static function CheckPermission($className,$action)
    {
        $rol = self::getRol();
        $permission = DB::table('role_permissions')
                    ->join('permissions','permissions.id','=','role_permissions.permission_id')
                    ->join('operations','operations.id','=','permissions.operation_id')
                    ->join('actions','actions.id','=','permissions.action_id')
                    ->where('operations.name','=',$className)
                    ->where('operations.module_id','=',$rol->module_id)
                    ->where('actions.name','=',$action)
                    ->where('role_permissions.role_id',$rol->id)
                    ->select('role_permissions.id')
                    ->first();
        return $permission;
    }
    public static function IconModule($module_id)
    {
        $class_icon = 'fa fa-square';
        switch ($module_id) {
            case 1:
                $class_icon = 'glyphicon glyphicon-hdd';
                break;
            
            case 2:
                $class_icon = 'fa fa-fw fa-puzzle-piece';
                break;
            case 3:
                $class_icon = 'glyphicon glyphicon-piggy-bank';
                break;
            case 4:
                $class_icon = 'fa fa-fw fa-heartbeat';
                break;
            case 6:
                $class_icon = 'fa fa-fw fa-money';
                break;
            case 7:
                $class_icon = 'fa fa-balance-scale';
                break;
            case 10:
                $class_icon = 'fa fa-map';
            case 11:
                $class_icon = 'fa fa-dollar';
                break;
        }
        return $class_icon;
    }
    public static function calculateAge($birth_date, $death_date)
    {
        $birth_date =  Carbon::createFromFormat('d/m/Y',$birth_date );
        if ($death_date) {
            $death_date = Carbon::createFromFormat('d/m/Y', $death_date);
            $age = $birth_date->diff($death_date)->format('%y años %m meses');
        }else {
            $age = $birth_date->diff(Carbon::now())->format('%y años %m meses');
        }
        return $age;
    }
    public static function calculateAgeYears($birth_date, $death_date)
    {
        $birth_date = Carbon::createFromFormat('d/m/Y',$birth_date);
        if ($death_date) {
            $death_date = Carbon::createFromFormat('d/m/Y', $death_date);
            $age = $birth_date->diffInYears($death_date);
        }else {
            $age = $birth_date->diffInYears(Carbon::now());
        }
        return $age;
    }
    public static function getCivilStatus($est, $gender)
    {
        switch ($est) {
            case 'S':
                return $gender ? ($gender == 'M' ? 'SOLTERO' : 'SOLTERA') : 'SOLTERO(A)' ;
                break;
            case 'D':
                return $gender ? ($gender == 'M' ? 'DIVORCIADO' : 'DIVORCIADA') : 'DIVORCIADO(A)' ;
                break;
            case 'C':
                return $gender ? ($gender == 'M' ? 'CASADO' : 'CASADA') : 'CASADO(A)' ;
                break;
            case 'V':
                return $gender ? ($gender == 'M' ? 'VIUDO' : 'VIUDA') : 'VIUDO(A)' ;
                break;
            case 'S':
                return $gender ? ($gender == 'M' ? 'SOLTERO' : 'SOLTERA') : 'SOLTERO(A)' ;
                break;
            default:
                return '';
                break;
        }
    }
    public static function extractMonth($date, $short=true)
    {
        if($date){
            if($short){

                return Carbon::parse($date)->formatLocalized("%b");
            }
            return Carbon::parse($date)->formatLocalized("%B");
        }
    }
    public static function extractYear($date)
    {
        if($date){
            return Carbon::parse($date)->formatLocalized("%Y");
        }
    }
    public static function getDateFormat($date, $size='short')
    {
        setlocale(LC_TIME, 'es_ES.utf8');
        if ($date) {
            if (self::verifyMonthYearDate($date) ) {
                $date = Carbon::createFromFormat('d/m/Y', '01/'.$date)->toDateString();
            }
            if ($size == 'short') {
                // return 05 MAY. 1983 // change %d-> %e for 5 MAY. 1983
                return Carbon::parse($date)->formatLocalized('%d %b. %Y'); //
            }elseif ($size == 'large') {
                return Carbon::parse($date)->formatLocalized('%d %B. %Y'); //
            }
        }
        return 'sin fecha';
    }
    public static function getRetFunCurrentProcedure()
    {
        $procedure_active = RetFunProcedure::where('is_enabled', 'true')->first();
        if (!$procedure_active) {
            Log::info("No existe ret fun procedure activo");
            abort(500);
        }
        return $procedure_active;
    }
    public static function getQuotaAidCurrentProcedure()
    {
        $procedure_active =  QuotaAidProcedure::where('is_enabled', 'true')->first();
        if (!$procedure_active) {
            Log::info("No existe ret fun procedure activo");
            abort(500);
        }
        return $procedure_active;
    }
    public static function sumTotalContributions($array, $fromView = false)
    {
        $total = 0;
        if (sizeof($array) > 0) {
            foreach ($array as $key => $value) {
                if ($fromView) {
                    // $value = json_encode($value);
                    $diff = Carbon::parse($value['start'])->diffInMonths(Carbon::parse($value['end'])) + 1;
                } else {
                    $diff = (Carbon::parse($value->end)->diffInMonths(Carbon::parse($value->start)) + 1);
                }
                if ($diff < 0) {
                    dd("error");
                }
                $total = $total + $diff;
            }
        }
        return $total;
    }
    public static function getGenderLabel($gender)
    {
        $label= 'genero no valido';
        switch($gender)
        {
            case 'F':
                $label = 'Femenino';
                break;
            case 'M':
                $label = 'Masculino';
                break;
        }
        return $label;
    }
    /*for legal opinion print*/
    public static function getDiscountCombinations($ret_fun_id)
    {
        $retirement_fund = RetirementFund::find($ret_fun_id);
        $array_discounts = array();
        $array_discounts_text = array();

        $array = DiscountType::all()->pluck('id');
        $results = array(array());
        foreach ($array as $element) {
            foreach ($results as $combination) {
                array_push($results, array_merge(array($element), $combination));
            }
        }
        foreach ($results as $value) {
            $sw = true;
            foreach ($value as $id) {
                // if (!$retirement_fund->discount_types()->find($id)) {
                if (!($retirement_fund->discount_types()->find($id)->pivot->amount > 0)) {
                    $sw = false;
                }
            }
            if ($sw) {
                $temp_total_discount = 0;
                $array_discounts_text = array();
                foreach ($value as $id) {
                    $amount = $retirement_fund->discount_types()->find($id)->pivot->amount;
                    if($amount > 0) {
                    $temp_total_discount = $temp_total_discount + $amount;
                    array_push($array_discounts_text, "que descontado el monto <b>".self::formatMoney($amount,true).' ('.self::convertir($amount). " BOLIVIANOS)</b> por concepto de ". $retirement_fund->discount_types()->find($id)->name);
                    }
                }
                $name = join(' - ', DiscountType::whereIn('id', $value)->orderBy('id', 'asc')->get()->pluck('name')->toArray());
                array_push($array_discounts, array('name' => $name, 'amount' => $temp_total_discount));
            }
        }
        $name = join(' - ', $array_discounts_text);
        $pos = strrpos($name, ' - que ');
        if ($pos !== false) {
            $name = substr_replace($name, ' y ', $pos, strlen(' - que '));
        }
        $name = str_replace(" -", ",", $name);
        if (sizeOf($array_discounts_text) > 0) {
            //$name = ', '. $name.", queda un saldo de <b>".self::formatMoney($array_discounts[sizeOf($array_discounts)-1]['amount'], true).' ('.self::convertir($array_discounts[sizeOf($array_discounts) - 1]['amount']) .' BOLIVIANOS)</b>.';            
            $name = ', '. $name.", queda un saldo de <b>".self::formatMoneyWithLiteral($retirement_fund->total_ret_fun).'</b>.';
        }
        return $name;
    }
    public static function updateAffiliatePersonalInfo($affiliate_id, $object)
    {
        $affiliate = Affiliate::find($affiliate_id);
        $affiliate->identity_card = $object->identity_card;
        $affiliate->first_name = $object->first_name;
        $affiliate->second_name = $object->second_name;
        $affiliate->last_name = $object->last_name;
        $affiliate->mothers_last_name = $object->mothers_last_name;
        $affiliate->surname_husband = $object->surname_husband;
        $affiliate->gender = $object->gender;
        $affiliate->birth_date = Util::verifyBarDate($object->birth_date) ? Util::parseBarDate($object->birth_date) : $object->birth_date;
        $affiliate->phone_number = $object->phone_number;
        $affiliate->cell_phone_number = $object->cell_phone_number;
        $affiliate->city_birth_id = $object->city_birth_id;
        $affiliate->city_identity_card_id = $object->city_identity_card_id;
        $affiliate->save();
    }
    public static function updateCreateSpousePersonalInfo($affiliate_id, $object)
    {
        $spouse = Spouse::where('affiliate_id',$affiliate_id)->first();
        if (!$spouse) {
            $spouse = new Spouse();
            $spouse->affiliate_id = $affiliate_id;
            $spouse->user_id = self::getAuthUser()->id;
            $spouse->registration = 0;
        }
        $spouse->identity_card = $object->identity_card;
        $spouse->first_name = $object->first_name;
        $spouse->second_name = $object->second_name;
        $spouse->last_name = $object->last_name;
        $spouse->mothers_last_name = $object->mothers_last_name;
        $spouse->surname_husband = $object->surname_husband;
        $spouse->civil_status = $object->civil_status;
        $spouse->birth_date = Util::verifyBarDate($object->birth_date) ? Util::parseBarDate($object->birth_date) : $object->birth_date;
        $spouse->city_birth_id = $object->city_birth_id;
        $spouse->city_identity_card_id = $object->city_identity_card_id;
        $spouse->date_death = $object->date_death ?? null;
        $spouse->reason_death = $object->reason_death ?? null;
        $spouse->save();
    }
    public static function classificationContribution($contribution_type_id, $breakdown_id, $total)
    {
        if($contribution_type_id){
            return $contribution_type_id;
        }
        switch ($breakdown_id) {
            case 1:
                return 10;
                break;
            case 3:
                return $total == 0 || ! isset($total) ? 3 : 2;
            case 5:
                return $total == 0 || ! isset($total) ? 5 : 4;
            case 10:
                return 1;
                break;
            default:
                return null;
            break;
        }
    }

    public static function getRegistration($birth_date = null, $last_name, $mothers_last_name, $first_name, $gender)
	{        
		if ($birth_date) {
			$birth_date_exploded = explode("-", $birth_date);
			$day = $birth_date_exploded[2];
			$month = $birth_date_exploded[1];
			$year = substr($birth_date_exploded[0], -2);
			$month_first_digit = substr($month, 0, 1);
            $month_second_digit = substr($month, 1, 1);
            
			if($last_name  != '') {
				$last_name_code = mb_substr($last_name, 0, 1);
			} else {
				$last_name_code = '';
            }
            
			if($mothers_last_name != '') {
				$mothers_last_name_code = mb_substr($mothers_last_name, 0, 1);
			} else {				
				$mothers_last_name_code = mb_substr($last_name, 1, 1);
            }
            
			if($first_name != '') {
				$first_name_code = mb_substr($first_name, 0, 1);
			} else {
				$first_name_code = '';
            }
            
			if($gender == "M") {
				return $year . $month . $day . $last_name_code . $mothers_last_name_code . $first_name_code;
            } elseif ($gender == "F") {
				if($month_first_digit == 0) {
					$month_second_digit = "5" .$month_second_digit;
				} elseif ($month_first_digit == 1) {
					$month_second_digit = "6" . $month_second_digit;
				}
				return $year . $month_second_digit . $day . $last_name_code . $mothers_last_name_code . $first_name_code;
			}
        }
        else {
            return null;
        }
    }

    // utils for prints

    public static function formatMonthYearLiteral($number)
    {
        $years = intval($number/12);
        $months = $number%12;
        $years_literal = ($years > 0) ? ( $years == 1 ? 'año' :'años') : null;
        $months_literal = ($months > 0) ? ( $months == 1 ? 'mes' :'meses') : null;
        return self::removeSpaces(($years_literal ? $years. ' '. $years_literal : null). ' '.($years_literal && $months_literal ? 'y ':''). ($months_literal ? $months . ' ' . $months_literal : null) );
    }
    public static function formatMoneyWithLiteral($value)
    {
        return self::formatMoney($value, true) .' ('. self::convertir($value).' BOLIVIANOS)';
    }
    public static function saveDay($date)
    {
        if(Carbon::parse($date)->isWeekend() ){
            if(Carbon::parse($date)->subDay(1)->isWeekend()){
                $date = Carbon::parse($date)->subDay(2)->toDateString();
            }else{
                $date = Carbon::parse($date)->subDay(1)->toDateString();
            }
        }
        return $date;

    }

    public static function completAidContributions($id, Carbon $start, Carbon $end_date) {
        $start_date = $start;
        $affiliate = Affiliate::find($id);
        while($start_date <= $end_date) {
            $date = $start_date;            
            $aid_contribution = AidContribution::where('month_year',$date->format('Y-m').'-01')->where('affiliate_id',$affiliate->id)->first();            
            if(!isset($aid_contribution->id)) {
                $aid_contribution = new AidContribution();
                $aid_contribution->user_id = Auth::user()->id;
                $aid_contribution->affiliate_id = $affiliate->id;                        
                $aid_contribution->month_year = $start_date->format('Y-m').'-01';
                $aid_contribution->type='PLANILLA';            
                $aid_contribution->dignity_rent = 0;
                $aid_contribution->quotable = 0;
                $aid_contribution->rent = 0;
                $aid_contribution->total = 0;
                $aid_contribution->interest = 0;
                $aid_contribution->save();                
            }
            $start_date->addMonth();
        }
        return;       
    }
    public static function completQuotaContributions($id, Carbon $start, Carbon $end_date) {
        $start_date = $start;
        $affiliate = Affiliate::find($id);
        while($start_date <= $end_date) {
            $date = $start_date;            
            $aid_contribution = Contribution::where('month_year',$date->format('Y-m').'-01')->where('affiliate_id',$affiliate->id)->first();
            if(!isset($aid_contribution->id)) {                
                $contribution = new Contribution();
                $contribution->user_id = Auth::user()->id;
                $contribution->affiliate_id = $affiliate->id;
                $contribution->degree_id = $affiliate->degree_id;
                $contribution->unit_id = $affiliate->unit_id;
                $contribution->breakdown_id = $affiliate->breakdown_id;
                $contribution->category_id = $affiliate->category_id;            
                $contribution->month_year = $start_date->format('Y-m').'-01';
                $contribution->type='Planilla';     
                $contribution->base_wage = 0;
                $contribution->seniority_bonus = 0;
                $contribution->study_bonus = 0;
                $contribution->position_bonus = 0;
                $contribution->border_bonus = 0;
                $contribution->east_bonus = 0;
                $contribution->public_security_bonus = 0;
                $contribution->deceased = 0;
                $contribution->natality = 0;
                $contribution->lactation = 0;
                $contribution->prenatal = 0;
                $contribution->subsidy = 0;
                $contribution->gain = 0;
                $contribution->payable_liquid = 0;
                $contribution->quotable = 0;
                $contribution->retirement_fund = 0;
                $contribution->mortuary_quota = 0;
                $contribution->total = 0;
                $contribution->interest = 0;        
                //$contribution->breakdown_id = 3;
                $contribution->save();

            }
            $start_date->addMonth();
        }
        return;       
    }

    public static function getHeadersInboxRetFunQuotaAid()
    {
        return [
            ['text' => "# Trámite", 'value' => "code"],
            ['text' => "CI Titular", 'align' => "left", 'value' => "ci"],
            ['text' => "Nombre del titular", 'value' => "name"],
            ['text' => "Modalidad", 'value' => "modality"],
            ['text' => "Regional", 'value' => "city"],
            ['text' => "Fecha de Recepción", 'value' => "date_reception"],
        ];
    }
    public static function getHeadersInboxTreasury()
    {
        return [
            ['text' => "# ", 'value' => "code"],
            ['text' => "CI Titular", 'align' => "left", 'value' => "ci"],
            ['text' => "Nombre del titular", 'value' => "name"],
            ['text' => "Tipo", 'value' => "modality"],
            ['text' => "Regional", 'value' => "city"],
            ['text' => "Fecha", 'value' => "date_reception"],
        ];
    }
    public static function getLastCode($model)
    {
        return optional($model::orderBy(DB::raw("regexp_replace(split_part(code, '/',2),'\D','','g')::integer"))->orderBy(DB::raw("split_part(code, '/',1)::integer"))->get()->last())->code;
    }
    public static function parseRequest($data, $prefix)
    {
        $values = array();
        foreach ($data as $key => $val) {
            if ($prefix == explode('_', $key)[0]) {
                $new_key = preg_replace('#'.$prefix.'_(.+)#i', '$1', $key);
                $values[$new_key] = $val;
            }
        }
        return $values;
    }

    public static function getLastCodeQM($model)
    {  
        return optional($model::orderBy(DB::raw("regexp_replace(split_part(code, '/',2),'\D','','g')::integer"))->orderBy(DB::raw("split_part(code, '/',1)::integer"))->where('code','not like','%A')->whereIn('procedure_modality_id',[8,9])->get()->last())->code;

    }

    public static function getLastCodeAM($model)
    { 
        return optional($model::orderBy(DB::raw("regexp_replace(split_part(code, '/',2),'\D','','g')::integer"))->orderBy(DB::raw("split_part(code, '/',1)::integer"))->where('code','not like','%A')->whereIn('procedure_modality_id',[13,14,15])->get()->last())->code;
    }



    public static function compoundInterest($contributions,Affiliate $affiliate) {
        $total = 0;
        $date_entry = Carbon::createFromFormat('m/Y', $affiliate->date_entry);
        $date_derelict = Carbon::createFromFormat('m/Y', $affiliate->date_derelict);
        $months_entry = ($date_entry->format('Y')*12)+$date_entry->format('m');
        $months_dereliect = ($date_derelict->format('Y')*12)+$date_derelict->format('m');
        $frecuency = 0;
        $interest_rate = 1.05; //replace by procedure interest rate
        foreach($contributions as $contribution) {
            $subtotal = round($contribution->total*pow($interest_rate,(($months_dereliect-($months_entry+$frecuency)))/12),2);
            $frecuency++;
            $total = round($total+$subtotal,2);
        }
        return $total;
    }

    public static function isChild($birth_date) {
        $today = Carbon::now();
        $birth_date = Carbon::createFromFormat('d/m/Y', $birth_date);
        $actual = $birth_date->addYear(18);
        if($birth_date->format('Y-m-d') > $today->format('Y-m-d')) {
            return true;
        } else {
            return false;
        }
    }
}