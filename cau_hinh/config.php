<?php
// cau_hinh/config.php
return [
  'app' => [
    'base_url' => 'http://localhost/webnhom77',
    'timezone' => 'Asia/Bangkok', // +07
  ],

  'db' => [
    'host' => '127.0.0.1',
    'name' => 'webnhom7',     // đổi đúng tên DB của bạn
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
  ],

  'mail' => [
    'from_email' => 'sonmoc24@gmail.com',
    'from_name'  => 'Crosc Vietnam',
    'smtp' => [
      'host' => 'smtp.gmail.com',
      'port' => 587,
      'username' => 'sonmoc24@gmail.com',
      'password' => 'xuufeqzyubrzyhfx', // app password
      'encryption' => 'tls',            // tls|ssl
    ],
  ],

  'security' => [
    'reset_ttl_minutes' => 20,
  ],
];
