<?php

namespace mattacosta\phd;

use phpdotnet\phd\Config;
use phpdotnet\phd\Format;
use phpdotnet\phd\Reader;
use phpdotnet\phd\ReaderKeeper;
use phpdotnet\phd\Render;

abstract class Package_IDE_Base extends Format {

  /**
   * Maps an XML element to a format method.
   *
   * These methods are called twice, once when the reader encounters the
   * <element> tag and again when encountering the </element> tag.
   *
   * NOTE: The renderer assumes that these methods begin with `format_`.
   */
  protected const ELEMENT_MAP = [
    'book'        => 'format_Book',
    'caution'     => 'format_Notes',
    'entry'       => 'format_ChangeLogEntry',
    'function'    => 'format_SeeAlsoEntry',
    'listitem'    => 'format_ParameterDescription',
    'methodparam' => 'format_MethodParameter',
    'methodname'  => 'format_SeeAlsoEntry',
    'member'      => 'format_Member',
    'note'        => 'format_Notes',
    'refentry'    => 'format_RefEntry',
    'refpurpose'  => 'format_RefPurpose',
    'refnamediv'  => 'format_SuppressedTags',
    'refsect1'    => 'format_RefSect1',
    'row'         => 'format_ChangeLogRow',
    'set'         => 'format_Set',
    'tbody'       => 'format_ChangeLogTbody',
    'tip'         => 'format_Notes',
    'warning'     => 'format_Notes',
  ];

  /**
   * Maps the text content of an XML element to a format method.
   */
  protected const TEXT_MAP = [
    'function'    => 'formatSeeAlsoEntryText',
    'initializer' => 'formatInitializerText',
    'methodname'  => 'formatSeeAlsoEntryText',
    'parameter'   => 'formatParameterText',
    'refname'     => 'formatRefNameText',
    'title'       => 'formatSuppressedText',
    'type'        => 'formatTypeText',
  ];

  // XML state.

  /**
   * Determines if this is the function reference section of the manual. If
   * `FALSE`, the formatter will encounter non-function entries such as built-in
   * classes, INI settings, and superglobals.
   *
   * @var bool $isFunctionRefSet
   */
  protected $isFunctionRefSet = FALSE;

  /**
   * Determines if the current chunk is in a whitelisted book.
   *
   * @var bool $isWhitelisted
   */
  protected $isWhitelisted = FALSE;

  /**
   * The "role" of a reference section, if any.
   *
   * @var string|bool $role
   */
  protected $role = FALSE;

  // Formatter state.

  /**
   * Temporary storage for content within a `<refentry>`.
   */
  protected $currentChunk = [
    'funcnames' => [],
    'methodparam'   => FALSE,
    'param' => [
      'name'        => FALSE,
      'type'        => FALSE,
      'description' => FALSE,
      'opt'         => FALSE,
      'initializer' => FALSE,
    ]
  ];

  /**
   * All collected information about the current function.
   */
  protected $currentFunction = [
    'name'          => NULL,
    'description'   => NULL,
    'params'        => [],
    'return' => [
      'type'        => NULL,
      'description' => NULL
    ]
  ];

  /**
   * Restrict the output to PHP and actively maintained "external" extensions.
   */
  protected $whitelist = [
    // Core
    'book.array',
    'book.classobj',
    'book.csprng',
    'book.datetime',
    'book.dir',
    'book.errorfunc',
    'book.exec',
    'book.filesystem',
    'book.filter',
    'book.funchand',
    'book.hash',
    'book.info',
    'book.mail',
    'book.math',
    'book.misc',
    'book.network',
    'book.outcontrol',
    'book.password',
    'book.phar',
    'book.reflection',
    // 'book.regex',  // Removed in 7.0 or later.
    'book.session',
    'book.spl',
    'book.stream',
    'book.strings',
    'book.tokenizer',
    'book.url',
    'book.var',
    // Bundled
    'book.apache',
    'book.bc',
    'book.calendar',
    'book.com',
    'book.ctype',
    'book.dba',
    'book.exif',
    'book.fileinfo',
    'book.ftp',
    'book.iconv',
    'book.gd',
    'book.intl',
    'book.json',
    'book.mbstring',
    'book.nsapi',
    'book.opcache',
    'book.pcntl',
    'book.pcre',
    'book.pdo',
    'book.posix',
    'book.sem',
    'book.shmop',
    'book.sockets',
    'book.sqlite3',
    'book.xmlrpc',
    'book.zlib',
    // External
    'book.bzip2',
    'book.curl',
    // 'book.dbase',
    // 'book.dom',
    // 'book.enchant',
    // 'book.fbsql',
    // 'book.gettext',
    // 'book.gmp',
    // 'book.ibase',
    // 'book.ifx',
    // 'book.imap',
    // 'book.ldap',
    'book.libxml',
    // 'book.mcrypt',
    // 'book.mhash',
    // 'book.msql',
    // 'book.mssql',
    // 'book.mysql',
    // 'book.mysqli',
    // 'book.mysqlnd',
    // 'book.oci8',
    'book.openssl',
    // 'book.pgsql',
    // 'book.pspell',
    // 'book.readline',
    // 'book.recode',
    'book.simplexml',
    // 'book.snmp',
    // 'book.soap',
    'book.sodium',  // Should be part of core.
    // 'book.sybase',
    // 'book.tidy',
    'book.odbc',
    // 'book.wddx',
    'book.xml',
    'book.xmlreader',
    'book.xmlwriter',
    'book.xsl',
    'book.zip',
  ];

  /**
   * {@inheritdoc}
   */
  public function CDATA($value) {}

  /**
   * {@inheritdoc}
   */
  public function TEXT($value) {}

  /**
   * {@inheritdoc}
   */
  public function UNDEF($open, $name, $attrs, $props) {}

  /**
   * {@inheritdoc}
   */
  public function appendData($data) {}

  /**
   * {@inheritdoc}
   */
  public function createLink($for, &$desc = NULL, $type = Format::SDESC) {}

  /**
   * {@inheritdoc}
   */
  public function transformFromMap($open, $tag, $name, $attrs, $props) {}

  /**
   * {@inheritdoc}
   */
  public function update($event, $value = NULL) {
    switch($event) {
      // case Render::CHUNK:
      //   $this->CHUNK($value);
      //   break;
      case Render::STANDALONE:
        $this->onRenderStandalone($value);
        break;
      case Render::INIT:
        $this->onRenderInitialize($value);
        break;
      case Render::FINALIZE:
        $this->onRenderFinalize($value);
        break;
      // case Render::VERBOSE:
      //   $this->VERBOSE($value);
      //   break;
    }
  }

  //#region Formatting (elements)

  public function format_Book($open, $name, $attrs, $props) {
    // Deny by default.
    $this->isWhitelisted = FALSE;
    if (isset($attrs[Reader::XMLNS_XML]['id']) && in_array($attrs[Reader::XMLNS_XML]['id'], $this->whitelist)) {
      $this->isWhitelisted = $open;
    }
  }

  public function format_ChangeLogEntry($open, $name, $attrs, $props) {
    // Not supported.
  }

  public function format_ChangeLogRow($open, $name, $attrs, $props) {
    // Not supported.
  }

  public function format_ChangeLogTbody($open, $name, $attrs, $props) {
    // Not supported.
  }

  public function format_Member($open, $name, $attrs, $props) {
    // Not supported.
  }

  public function format_MethodParameter($open, $name, $attrs, $props) {
    if (!$this->isFunctionRefSet || !$this->isWhitelisted || $this->role != 'description') {
      return;
    }
    if ($open) {
      // Indicate that future initializers belong to this parameter.
      $this->currentChunk['methodparam'] = TRUE;

      if (isset($attrs[Reader::XMLNS_DOCBOOK]['choice']) && $attrs[Reader::XMLNS_DOCBOOK]['choice'] == 'opt') {
        $this->currentChunk['param']['opt'] = TRUE;
      }
      else {
        $this->currentChunk['param']['opt'] = FALSE;
      }
    }
    else {
      // Compile the collected data into a usable parameter.
      $param = [
        'name' => $this->currentChunk['param']['name'],
        'type' => $this->currentChunk['param']['type'],
        'optional' => $this->currentChunk['param']['opt'],
        'initializer' => $this->currentChunk['param']['initializer'] !== FALSE
          ? $this->currentChunk['param']['initializer']
          : FALSE,
      ];
      $this->currentFunction['params'][$param['name']] = $param;

      // Reset.
      $this->currentChunk['methodparam'] = FALSE;
      $this->currentChunk['param'] = [
        'name'        => FALSE,
        'type'        => FALSE,
        'description' => FALSE,
        'opt'         => FALSE,
        'initializer' => FALSE,
      ];
    }
  }

  public function format_Notes($open, $name, $attrs, $props) {
    // Not supported.
  }

  public function format_ParameterDescription($open, $name, $attrs, $props) {
    // Not supported.
  }

  public function format_RefEntry($open, $name, $attrs, $props) {
    if (!$this->isFunctionRefSet || !$this->isWhitelisted) {
      return;
    }
    if ($open) {
      // Reset all stored data.
      $this->currentChunk = [
        'funcnames' => [],
        'methodparam'   => FALSE,
        'param' => [
          'name'        => FALSE,
          'type'        => FALSE,
          'description' => FALSE,
          'opt'         => FALSE,
          'initializer' => FALSE,
        ]
      ];
      $this->currentFunction = [
        'name'          => NULL,
        'description'   => NULL,
        'params'        => [],
        'return' => [
          'type'        => NULL,
          'description' => NULL
        ]
      ];

      // $this->currentFunction['manualid'] = $attrs[Reader::XMLNS_XML]['id'];
    }
    else {
      $this->writeChunk();
    }
  }

  public function format_RefPurpose($open, $name, $attrs, $props) {
    if ($this->isFunctionRefSet && $this->isWhitelisted && $open) {
      $this->currentFunction['description'] = str_replace("\n", '', trim(ReaderKeeper::getReader()->readString()));
    }
  }

  public function format_RefSect1($open, $name, $attrs, $props) {
    if (!$this->isFunctionRefSet || !$this->isWhitelisted) {
      return;
    }
    if ($open) {
      if (isset($attrs[Reader::XMLNS_DOCBOOK]['role']) && $attrs[Reader::XMLNS_DOCBOOK]['role']) {
        $this->role = $attrs[Reader::XMLNS_DOCBOOK]['role'];
      }
      else {
        $this->role = FALSE;
      }
    }
    else {
      $this->role = FALSE;
    }
    // if ($this->role == 'errors') {
    //   $this->format_Errors($open, $name, $attrs, $props);
    // }
    // else if ($this->role == 'returnvalues') {
    //   $this->format_ReturnValues($open, $name, $attrs, $props);
    // }
  }

  public function format_SeeAlsoEntry($open, $name, $attrs, $props) {
    // Not supported.
  }

  public function format_Set($open, $name, $attrs, $props) {
    if (isset($attrs[Reader::XMLNS_XML]['id']) && $attrs[Reader::XMLNS_XML]['id'] == 'funcref') {
      $this->isFunctionRefSet = $open;
    }
  }

  public function format_SuppressedTags($open, $name, $attrs, $props) {
    // Does nothing.
  }

  //#endregion

  //#region Formatting (text)

  public function formatInitializerText($value, $tag) {
    if (!$this->isFunctionRefSet || !$this->isWhitelisted) {
      return;
    }
    if (!$this->currentChunk['methodparam']) {
      return;
    }
    $this->currentChunk['param']['initializer'] = $value;
  }

  public function formatParameterText($value, $tag) {
    if (!$this->isFunctionRefSet || !$this->isWhitelisted) {
      return;
    }
    if ($this->currentChunk['methodparam']) {
      $this->currentChunk['param']['name'] = $value;
    }
    // if ($this->role == 'parameters') {
    //   $this->currentChunk['currentParam'] = trim($value);
    // }
  }

  public function formatRefNameText($value, $tag) {
    if ($this->isFunctionRefSet || !$this->isWhitelisted) {
      // If the entry is an alias, it may contain multiple names.
      $this->currentChunk['funcnames'][] = $value;
    }
  }

  public function formatSeeAlsoEntryText($value, $tag) {
    // Does nothing.
  }

  public function formatSuppressedText($value, $tag) {
    // Does nothing.
  }

  public function formatTypeText($value, $tag) {
    if (!$this->isFunctionRefSet || !$this->isWhitelisted) {
      return;
    }
    if ($this->role == 'description') {
      if (!$this->currentChunk['methodparam']) {
        $this->currentFunction['return']['type'] = $value;
      }
      else {
        $this->currentChunk['param']['type'] = $value;
      }
    }
  }

  //#endregion

  /**
   * Determines if a name is safe to output.
   */
  protected function isValidName($name) {
    // Invalid entry.
    if (strpos($name, ' ') !== FALSE) {
      return FALSE;
    }

    if (!$this->isFunctionRefSet) {
      // Only include superglobals.
      // if (strpos($value, '$') === 0) {
      //   return TRUE;
      // }
      return FALSE;
    }
    else {
      // No support for static properties, class constants, or namespaced types.
      if (strpos($name, '::') !== FALSE || strpos($name, '\\') !== FALSE) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Renders a function in an appropriate format.
   *
   * @param array $definition
   *   An associative array containing the function defintion.
   *   - 'name': (string)
   *   - 'description': (string|null)
   *   - 'params': (array)
   *   - 'return': (array)
   */
  abstract protected function renderFunction(array $definition);

  // abstract protected function renderGlobal();

  /**
   * Finalizes the output of a format.
   */
  protected function onRenderFinalize($value) {}

  /**
   * Initializes a format prior to rendering any elements.
   */
  protected function onRenderInitialize($value) {
    $this->setOutputDir(Config::output_dir());

    $dir = $this->getOutputDir();
    if (!file_exists($dir)) {
      if(!mkdir($dir, 0777)) {
        throw new \Exception('Unable to create output directory');
      }
    }
  }

  /**
   * @todo Document onRenderStandalone().
   */
  protected function onRenderStandalone($value) {
    $this->registerElementMap(self::ELEMENT_MAP);
    $this->registerTextMap(self::TEXT_MAP);
  }

  /**
   * Outputs any collected data from a reference entry.
   */
  protected function writeChunk() {
    // Not a function.
    if (count($this->currentChunk['funcnames']) == 0) {
      return;
    }

    foreach ($this->currentChunk['funcnames'] as $name) {
      if ($this->isValidName($name)) {
        $this->currentFunction['name'] = $name;
        $this->renderFunction($this->currentFunction);
        break;
      }
    }
  }

}
