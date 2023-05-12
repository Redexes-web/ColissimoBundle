<?php

namespace CleverAge\ColissimoBundle\Model\Shipping\Enum;

interface LabelFileType
{
    public const ZPL = [
        'mime' => 'application/x-zpl',
        'extension' => 'zpl',
    ];
    public const DPL = [
        'mime' => 'application/x-dpl',
        'extension' => 'dpl',
    ];
    public const PDF = [
        'mime' => 'application/pdf',
        'extension' => 'pdf',
    ];
}
