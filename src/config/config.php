<?php
$path = 'simpel';
return [
    'name'=>'Simpel',
    'path'=>$path,
    'icon' => 'fa-envelope',
    'description' => 'Sistem Informasi Pelayanan Terkait Administrasi Masyarakat',
    'module' =>
    array(
        [
            'name' => 'Dashboard',
            'route' => $path . '.dashboard',
            'path' => $path . '/dashboard',
        ],
        [
            'name' => 'Permohonan',
            'route' => $path . '.penduduk',
            'path' => $path . '/permohonan',

        ],
        [
            'name' => 'Pejabat',
            'route' => $path . '.penduduk',
            'path' => $path . '/pejabat',

        ]
    )
];
