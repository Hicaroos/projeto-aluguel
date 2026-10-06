<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 2.5cm 2.5cm 2.8cm;
        }

        body {
            font-family: 'DejaVu Serif', serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #111;
        }

        footer {
            position: fixed;
            bottom: -1.6cm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8.5pt;
            color: #666;
        }

        footer .page-number:after {
            content: "Página " counter(page) " de " counter(pages);
        }

        h1 {
            font-size: 14pt;
            text-align: center;
            margin: 0 0 18pt;
        }

        h2 {
            font-size: 11pt;
            margin: 16pt 0 6pt;
            page-break-after: avoid;
        }

        h3 {
            font-size: 11pt;
            margin: 12pt 0 6pt;
            page-break-after: avoid;
        }

        p {
            margin: 0 0 8pt;
            text-align: justify;
        }

        ul,
        ol {
            margin: 0 0 8pt;
            padding-left: 18pt;
        }

        li p {
            margin: 0 0 4pt;
        }

        blockquote {
            margin: 0 0 8pt 18pt;
            font-style: italic;
        }

        hr {
            border: 0;
            border-top: 1px solid #999;
            margin: 12pt 0;
        }
    </style>
</head>
<body>
    <footer><span class="page-number"></span></footer>

    <main>
        {!! $body !!}
    </main>
</body>
</html>
