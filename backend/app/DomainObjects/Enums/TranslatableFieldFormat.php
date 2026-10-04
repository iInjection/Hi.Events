<?php

namespace HiEvents\DomainObjects\Enums;

enum TranslatableFieldFormat: string
{
    use BaseEnum;

    case TEXT = 'TEXT';
    case MULTILINE = 'MULTILINE';
    case HTML = 'HTML';
    case LIST = 'LIST';
}
