<?php
namespace CodeTools;


class ProcessException extends \Exception {
  public function __construct($message) {
    parent::__construct($message);
  }
}
