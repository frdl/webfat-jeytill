<?php 

/**
* Fork of: https://github.com/rosell-dk/handsdown
* By Frdlweb
*/


namespace Webfan\Webfat;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\ConverterInterface;
use Spyc;

class Jeytill
{
    protected $allowPhp = false;
    protected $allowHtml = false;
    protected $allowHtmlFiles  =false;
    protected $options;
    protected $converter;
    protected $page_md;

    public function __construct(array $options = null, $allowPhp = false, $allowHtml=false, $allowHtmlFiles=false, ConverterInterface $converter = null)
    {
        $this->allowHtml=$allowHtml;
        $this->allowHtmlFiles=$allowHtmlFiles;
        $this->allowPhp=$allowPhp;
        $this->options = $this->getDefaultOptions($options);
        $this->converter =(null !== $converter)
                     ? $converter
                     : new CommonMarkConverter( $this->options['parser'] );
    }

    public function __invoke($type, $slug, $content_variable = '')
    {
        $includePhp = false;

          $filename_without_extension = ( isset($this->options[$type.'-dir']))
           ?  rtrim( $this->options[$type.'-dir'], '//\\') . '/' . $slug
           : rtrim( $this->options['content-dir'], '//\\') . '/' . $type . '/' . $slug;


          if(is_dir($filename_without_extension)){
              $filename_without_extension.='/index';
          }

          if (is_file($filename_without_extension . '.md')) {
            $filename = $filename_without_extension . '.md';
          }
          else if (is_file($filename_without_extension . \DIRECTORY_SEPARATOR.'index.md')) {
              $filename = $filename_without_extension . \DIRECTORY_SEPARATOR.'index.md';
          }
          else if (('themes'===$type || true===$this->allowHtmlFiles) && is_file($filename_without_extension . '.html')) {
             $filename = $filename_without_extension . '.html';
          }
          else if (('themes'===$type || true===$this->allowHtmlFiles) && is_file($filename_without_extension . '.htm')) {
              $filename = $filename_without_extension . '.htm';
          }
          else if (true===$this->allowPhp && is_file($filename_without_extension . '.php')) {
             $filename = $filename_without_extension . '.php';
             $includePhp = true;
          }
          else {
               //echo 'not found: ' . $filename_without_extension . '<br><br>';
               return false;
          }

          if(true===$this->allowPhp && true === $includePhp){
             ob_start();
             require $filename;
             $content = ob_get_clean();
          }else{
             $content = file_get_contents($filename);
          }
          


          $this->page_md = $content;
          $this->parseFrontmatter($content);

          $content = $this->mustache_substitute($content, $content_variable);

    if ($type !== 'themes') {
		
          if(true !== $this->allowHtml){
		  	 $content = strip_tags($content);
	   }		
		
          $content = $this->converter->convert($content);

        // Wrap it in template, if there is one
        //global $theme;
        $wrapped_content = $this('themes', $this->options['frontmatter']['theme'] . '/' . $type . '-' . $slug, $content);
        if ($wrapped_content !== FALSE) {
          $content = $wrapped_content;
        } else {
          $wrapped_content = $this('themes', $this->options['frontmatter']['theme'] . '/' . $type, $content);
          if ($wrapped_content !== FALSE) {
             $content = $wrapped_content;
          }
        }
          
      }

         
      return $content;
    }

    protected function mustache_substitute($text, $content_variable)
    {
        $self = &$this;
      return preg_replace_callback('/{{((?:[^}]|}[^}])+)}}/', function($matches) use ($content_variable, &$self) {
        $tag = trim($matches[1]);

        switch ($tag) {
          case 'main':
          case 'content':
             return $content_variable;
          case 'host':
             return $_SERVER['HTTP_HOST'];
          case 'theme-name':
             return $self->options['frontmatter']['theme'];
          case 'root-url':
		if(!isset($self->options['frontmatter']['root-url'])){
		  return 'https://'.$_SERVER['HTTP_HOST'];
		}
            return $self->options['frontmatter']['root-url'];
          case 'theme-url':
        //return 'https://'.$_SERVER['HTTP_HOST'].$self->options['themes-dir'] .$self->options['frontmatter']['theme'];
            return rtrim($self->options['themes-dir'], '//\\'). '/' .$self->options['frontmatter']['theme'];
        }
		  
        if (isset( $self->options['frontmatter'][$tag])) {
            return  $self->options['frontmatter'][$tag];
        }

        $block_html = $self('blocks', $tag, $content_variable);
        if ($block_html !== false) {
          return $block_html;
        }

        return "{{ $tag }}";        
      }, $text);
    }

    protected function parseFrontmatter(&$text_md)
    {
      if (strncmp($text_md, "+++", 3) === 0) {
        // TOML format, but only partly supported
        $endpos = strpos($text_md, '+++', 3);
        $frontmatter = trim(substr($text_md, 3, $endpos - 3));
        $text_md = substr($text_md, $endpos + 3);

        $lines = preg_split("/\\r\\n|\\r|\\n/", $frontmatter);

        $group_prefix = '';
        foreach ($lines as $line) {
          // Grouping
          if (preg_match('/\[(.*)\]/', $line, $matches)) {
              $group_prefix = $matches[1] . '.';
          }
          // String assignments
          if (preg_match('/([\w-]+)\\s*=\\s*([\'"])(.*)\\2/', $line, $matches)) {
             $this->options['frontmatter'][$group_prefix . $matches[1]] = $matches[3];
          }
        }
       }
         
     if (strncmp($text_md, "---", 3) === 0) {
        $endpos = strpos($text_md, '---', 3);
        $frontmatter = trim(substr($text_md, 3, $endpos - 3));
        $text_md = substr($text_md, $endpos + 3);

        $array = Spyc::YAMLLoadString($frontmatter);

        foreach ($array as $index => $item) {
           $this->options['frontmatter'][$index] = $item;
        }
      }
    }

    protected function getDefaultOptions(array $options = null)
    {
        if(null===$options){
          $options = [];
        }



        if(!isset($options['dir'])){
            //$options['dir'] = getcwd();
            $options['dir'] = $_SERVER['DOCUMENT_ROOT'] . \DIRECTORY_SEPARATOR.'..';
        }
        if(!isset($options['configfile'])){
            $options['configfile'] = $options['dir'] . \DIRECTORY_SEPARATOR . '_config.yaml';
        }
        if(!isset($options['content-dir'])){
            $options['content-dir'] = is_dir($options['dir'] . \DIRECTORY_SEPARATOR.'content')
                          ? \DIRECTORY_SEPARATOR.'content'.\DIRECTORY_SEPARATOR
                          : \DIRECTORY_SEPARATOR;
        }
        if(!isset($options['themes-dir'])){
            $options['themes-dir'] =$options['content-dir'].\DIRECTORY_SEPARATOR.'themes'.\DIRECTORY_SEPARATOR;
        }
        if(!isset($options['pages-dir'])){
            $options['pages-dir'] =$options['content-dir'].\DIRECTORY_SEPARATOR.'pages'.\DIRECTORY_SEPARATOR;
        }
        if(!isset($options['blocks-dir'])){
            $options['blocks-dir'] =$options['content-dir'].\DIRECTORY_SEPARATOR.'blocks'.\DIRECTORY_SEPARATOR;
        }

        $frontmatter = file_exists($options['configfile'])
                    ? Spyc::YAMLLoad($options['configfile'])
                    : [
                        'theme' =>'webfantized-standard-theme',
                    ];

		
		$options = array_merge( [
               'parser' => [
                   'html_input' => 'strip',
                   'allow_unsafe_links' => false,
               ],
			], $options);
		
		$options['frontmatter'] = array_merge([
                        'theme' =>'webfantized-standard-theme',
				                'root-url' =>  'https://'.$_SERVER['HTTP_HOST'],
                    ], $frontmatter);

    
        return $options;
    }
}
