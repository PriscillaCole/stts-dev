<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

//one to many relationship with formsr4
    public function form_sr4()
    {
        return $this->belongsTo(FormSr4::class);
    }

//one to many relationship with formsr5
    public function form_qds()
    {
        return $this->belongsTo(FormQds::class);
    }

//one to many relationship with formsr6
    public function form_sr6()
    {
        return $this->belongsTo(FormSr6::class);
    }

//one to many relationship with importexport permits
    public function import_export_permit()
    {
        return $this->belongsTo(ImportExportPermit::class);
    }
}
