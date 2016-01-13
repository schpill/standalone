<?php

namespace Myerror\Formatter;

interface FormatterInterface
{
    public function format($e);

    public function setErrorLimit($limit);

    public function getErrorLimit();
}
