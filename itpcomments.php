<?php
/**
 * @package         ITPComments
 * @subpackage      Plugins
 * @author          Todor Iliev
 * @copyright       Copyright (C) 2015 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license         http://www.gnu.org/licenses/gpl-3.0.en.html
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

/**
 * ITP Comments Plugin
 *
 * @package        ITPComments
 * @subpackage     Plugins
 */
class plgContentItpComments extends JPlugin
{
    /**
     * A JRegistry object holding the parameters for the plugin
     *
     * @var    Joomla\Registry\Registry
     * @since  1.5
     */
    public $params = null;

    private $currentView = "";
    private $currentTask = "";
    private $currentOption = "";
    private $currentLayout = "";

    /**
     * Add social buttons into the article after content.
     *
     * @param    string    $context The context of the content being passed to the plugin.
     * @param    object    $article The article object.  Note $article->text is also available
     * @param    Joomla\Registry\Registry $params  The article params
     * @param    int       $page    The 'page' number
     *
     * @return string
     */
    public function onContentAfterDisplay($context, &$article, &$params, $page = 0)
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        if ($app->isAdmin()) {
            return null;
        }

        $doc = JFactory::getDocument();
        /**  @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp("html", $docType) != 0) {
            return null;
        }

        // Get request data
        $this->currentOption = $app->input->getCmd("option");
        $this->currentView   = $app->input->getCmd("view");
        $this->currentTask   = $app->input->getCmd("task");
        $this->currentLayout = $app->input->getCmd("layout");

        if ($this->isRestricted($article, $context)) {
            return null;
        }

        // Get locale code automatically
        if (!$this->params->get("locale", "")) {
            $lang         = JFactory::getLanguage();
            $locale       = $lang->getTag();

            $facebookLocale = str_replace("-", "_", $locale);
            $disqusLocale   = substr($facebookLocale, 0, -3);
        } else {
            $facebookLocale = str_replace("-", "_", $this->params->get("locale", ""));
            $disqusLocale   = substr($facebookLocale, 0, -3);
        }

        $content = '<div style="clear:both;"></div><div class="itp-comments">';

        // Generate and return content
        switch ($this->params->get("platform", "facebook")) {
            case "disqus":
                $content .=  $this->getDisqusComments($article, $disqusLocale);
                break;

            default: // Facebook
                $content .=  $this->getFacebookComments($article, $facebookLocale);
                break;
        }

        $content .= '</div><div style="clear:both;"></div>';

        return $content;
    }

    private function isRestricted($article, $context)
    {
        switch ($this->currentOption) {
            case "com_content":
                $result = $this->isContentRestricted($article, $context);
                break;

            case "com_crowdfunding":
                $result = $this->isCrowdFundingRestricted($context);
                break;

            case "com_userideas":
                $result = $this->isUserIdeasRestricted($context);
                break;

            default:
                $result = true;
                break;
        }

        return $result;
    }

    /**
     * Checks allowed articles, excluded categories/articles,... for component com_content
     *
     * @param object $article
     * @param string $context
     *
     * @return bool
     */
    private function isContentRestricted(&$article, $context)
    {
        // Check for correct context
        if (strcmp("com_content.article", $context) !=0) {
            return true;
        }

        /** Check for selected views, which will display the comments. **/
        /** If there is a specific set and do not match, return an empty string.**/
        $showInArticles = $this->params->get('content_display_articles');
        if (!$showInArticles and (strcmp("article", $this->currentView) == 0)) {
            return true;
        }

        // Exclude articles
        $excludeArticles = $this->params->get('excludeArticles');
        if (!empty($excludeArticles)) {
            $excludeArticles = explode(',', $excludeArticles);
        }
        settype($excludeArticles, 'array');
        JArrayHelper::toInteger($excludeArticles);

        // Excluded categories
        $excludedCats = $this->params->get('excludeCats');
        if (!empty($excludedCats)) {
            $excludedCats = explode(',', $excludedCats);
        }
        settype($excludedCats, 'array');
        JArrayHelper::toInteger($excludedCats);

        // Included Articles
        $includedArticles = $this->params->get('includeArticles');
        if (!empty($includedArticles)) {
            $includedArticles = explode(',', $includedArticles);
        }
        settype($includedArticles, 'array');
        JArrayHelper::toInteger($includedArticles);

        if (!in_array($article->id, $includedArticles)) {
            // Check excluded articles
            if (in_array($article->id, $excludeArticles) or in_array($article->catid, $excludedCats)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks for restrictions.
     *
     * @param string $context
     *
     * @return bool
     */
    private function isCrowdFundingRestricted($context)
    {
        // Check for correct context.
        if (strcmp("com_crowdfunding.comments", $context) != 0) {
            return true;
        }

        if (!$this->params->get("crowdfunding_display_details", 0)) {
            return true;
        }

        return false;
    }

    /**
     * Checks allowed context.
     *
     * @param string $context
     *
     * @return bool
     */
    private function isUserIdeasRestricted($context)
    {
        // Check for correct context.
        if (strcmp("com_userideas.details", $context) != 0) {
            return true;
        }

        if (!$this->params->get("userideas_display_details", 0)) {
            return true;
        }

        return false;
    }

    /**
     * Generate Facebook comments code.
     *
     * @param   object $article
     * @param   string $locale
     *
     * @return  string
     */
    private function getFacebookComments(&$article, $locale)
    {
        $url = $this->getUrl($article);

        $html = array();

        if ($this->params->get("include_root_div", 1)) {
            $html[] = '<div id="fb-root"></div>';
        }

        if ($this->params->get("load_js_library", 1)) {
            $html[] = '
<script>(function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/'.$locale.'/sdk.js#xfbml=1&appId='.$this->params->get("api_id").'&version=v2.4";
  fjs.parentNode.insertBefore(js, fjs);
}(document, "script", "facebook-jssdk"));
</script>';
        }

        $width = "";
        if ($this->params->get("width")) {
            $width = 'data-width="'.$this->params->get("width").'"';
        }

        $numPosts = 'data-numposts="'.$this->params->get("number_of_posts", 5).'"';
        $colorSchema = 'data-colorscheme="'.$this->params->get("color_scheme", "light").'"';
        $orderBy = 'data-order-by="'.$this->params->get("order_by", "social").'"';

        $html[] = '<div class="fb-comments" data-href="' . $url . '" '.$width.' '.$numPosts.' '. $colorSchema .' '. $orderBy .' ></div>';

        return implode("\n", $html);
    }

    /**
     * Generate Disqus comments code.
     *
     * @param   object $article
     * @param   string $locale
     *
     * @return  string
     */
    private function getDisqusComments(&$article, $locale)
    {
        $url = $this->getUrl($article);
        $identifier = $this->getIdentifier();

        $html = array();

        $html[] = '<div id="disqus_thread"></div>
    <script type="text/javascript">
        var disqus_shortname = "'.$this->params->get('disqus_shortname').'";
        var disqus_url = "'.$url.'";
        var disqus_identifier = "'.$identifier.'";

        var disqus_config = function () {
          this.language = "'.$locale.'";
        };

        (function() {
            var dsq = document.createElement("script"); dsq.type = "text/javascript"; dsq.async = true;
            dsq.src = "//" + disqus_shortname + ".disqus.com/embed.js";
            (document.getElementsByTagName("head")[0] || document.getElementsByTagName("body")[0]).appendChild(dsq);
        })();
    </script>
    <noscript>Please enable JavaScript to view the <a href="http://disqus.com/?ref_noscript">comments powered by Disqus.</a></noscript>
    <a href="http://disqus.com" class="dsq-brlink">comments powered by <span class="logo-disqus">Disqus</span></a>
    ';

        return implode("\n", $html);
    }

    /**
     * @param object $article
     *
     * @return string
     */
    private function getUrl(&$article)
    {
        $url    = JUri::getInstance();
        $domain = $url->getScheme() . "://" . $url->getHost();

        switch ($this->currentOption) {
            case "com_content":
                $url = $domain.JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catslug), false);
                break;

            case "com_crowdfunding":
                $url = $article->link;
                break;

            case "com_userideas":
                $url = $domain.JRoute::_($article->link);
                break;
            default:
                $url = "";
                break;
        }

        // Filter the URL
        $filter = JFilterInput::getInstance();
        $url    = $filter->clean($url);

        return $url;
    }

    /**
     * Return an identifier.
     *
     * @return string
     */
    private function getIdentifier()
    {
        $path   = JUri::getInstance()->getPath();

        return md5($path);
    }
}
