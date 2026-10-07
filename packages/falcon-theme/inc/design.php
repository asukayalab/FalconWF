<?php
namespace FalconTheme;
/** Theme owns the design contract; FP provides the human administration boundary. */
final class Design {
    public static function fonts(): array {
        return ['sans'=>['Arial / Helvetica','Arial,Helvetica,sans-serif'], 'serif'=>['Georgia / Times','Georgia,"Times New Roman",serif'], 'system'=>['System UI','system-ui,-apple-system,"Segoe UI",sans-serif'], 'mono'=>['Monospace','ui-monospace,Consolas,monospace']];
    }
    public static function fields(): array {
        $fields=['body_font'=>['Font teks','font'], 'heading_font'=>['Font judul H1–H6','font'], 'ink'=>['Warna teks','color'], 'paper'=>['Warna latar halaman','color'], 'accent'=>['Warna aksen / tautan','color'], 'muted'=>['Warna teks sekunder','color'], 'line'=>['Warna garis','color'], 'body_size'=>['Ukuran teks (px)','number',14,24], 'line_height'=>['Jarak baris teks','number',1.2,2], 'content_width'=>['Lebar maksimum konten (px)','number',640,1600], 'spacing'=>['Jarak tepi konten (px)','number',16,96]];
        foreach ([1=>[28,120],2=>[24,80],3=>[20,64],4=>[18,48],5=>[16,40],6=>[14,32]] as $level=>[$min,$max]) { $fields['h'.$level]=['Ukuran H'.$level.' maksimum (px)','number',$min,$max]; }
        return $fields;
    }
    public static function validate(mixed $input): array|\WP_Error {
        if (!is_array($input) || array_diff(array_keys($input),array_keys(self::fields()))) { return new \WP_Error('FWF_VALIDATION','Field desain tidak dikenal.'); }
        $values=[];
        foreach ($input as $key=>$value) {
            if (!is_string($value) && !is_int($value) && !is_float($value)) { return new \WP_Error('FWF_VALIDATION','Nilai desain harus berupa teks atau angka.'); }
            $value=trim((string)$value); if ($value==='') { continue; }
            $field=self::fields()[$key];
            $valid=match($field[1]) {
                'font'=>isset(self::fonts()[$value]),
                'color'=>(bool)preg_match('/^#[a-fA-F0-9]{6}$/D',$value),
                'number'=>(bool)preg_match('/^\d{1,4}(?:\.\d{1,2})?$/D',$value) && (float)$value>=$field[2] && (float)$value<=$field[3],
            };
            if (!$valid) { return new \WP_Error('FWF_VALIDATION','Nilai tidak valid: '.$field[0].'.'); }
            $values[$key]=$field[1]==='number'?(string)(float)$value:strtolower($value);
        }
        ksort($values); return $values;
    }
    public static function values(): array {
        $values=self::validate(get_option('fwf_design_'.get_stylesheet(),[]));
        return is_wp_error($values)?[]:$values;
    }
    public static function revision(): string { return hash('sha256',get_stylesheet().'|'.wp_json_encode(self::values())); }
    public static function css(): string {
        $css='';
        foreach (self::values() as $key=>$value) {
            $field=self::fields()[$key];
            if ($field[1]==='font') { $value=self::fonts()[$value][1]; }
            elseif ($field[1]==='number' && $key!=='line_height') { $value.='px'; }
            $css.='--fwf-'.str_replace('_','-',$key).':'.$value.';';
        }
        return $css===''?'':':root{'.$css.'}';
    }
}
