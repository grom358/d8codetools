d8codetools
===========

Drupal 8 Code Tools

## Install

Run 'composer install' to install dependencies. Then optionally make the tools executable and put into your path, eg:
```bash
$ cd d8codetools
$ composer install
$ chmod a+rx *.php
$ ln -sf function_replace.php /usr/local/bin/function_replace.php
```

## Tools
### function_replace.php
Replaces procedural function calls to class methods, adding the use declaration if necessary.

**Usage**: function_replace.php [function_name] [qualified_class_name] [alias] [static_method]
* *function_name* - the procedural function name, eg. check_plain
* *qualified_class_name* - the fully qualified class name, eg. Drupal\Component\Utility\String
* *alias* - the alias to use if there is a conflicting class name, eg. UtilityString. Note if the alias also conflicts the error message 'Unable to insert use declaration!' will occur.
* *static_method* - the name of the static method to call in place of the function_name, eg. checkPlain

The tool will print the file name and a message if it is unable to process the file.
