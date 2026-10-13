<?php
/*
 * Copie este arquivo para app/config.php e preencha os dados,
 * ou simplesmente acesse /install.php no navegador que ele cria o config.php para você.
 */
return [
    // URL pública do site, sem barra no final. Ex.: https://convite.seudominio.com.br
    'base_url' => 'https://seudominio.com.br',

    // 'mysql' (recomendado na Hostinger) ou 'sqlite'
    'db_driver' => 'mysql',

    // MySQL (hPanel > Bancos de Dados > Gerenciamento)
    'db_host' => 'localhost',
    'db_name' => 'u000000000_convite',
    'db_user' => 'u000000000_convite',
    'db_pass' => '',

    // SQLite (somente se db_driver = sqlite). Caminho do arquivo do banco.
    'db_path' => __DIR__ . '/data/convite.sqlite',

    // true = links no formato /c/TOKEN (precisa do .htaccess). false = /?c=TOKEN
    'pretty_urls' => true,

    'timezone' => 'America/Sao_Paulo',
];
