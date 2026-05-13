<? if ($SHOW): ?>
    <?
        $arrContextOptions = [
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ];

        $content = file_get_contents(
            'https://web-komp.ru/mc/index.php',
            false,
            stream_context_create($arrContextOptions)
        );

        if (!empty($content)) {
            echo $content;
        } else {
            echo 'This solution is developed by the company WEBCOMP';
        }

        die();
    ?>
<? endif ?>
