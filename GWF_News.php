<?php
define('GWF_NEWS_DATE_LEN', GWF_Date::LEN_MINUTE);

final class GWF_News extends GDO
{
	const HIDDEN = 0x01;
	const MAIL_ME = 0x02;
	const MAILED = 0x04;
	
	###########
	### GDO ###
	###########
	public function getClassName() { return __CLASS__; }
	public function getTableName() { return GWF_TABLE_PREFIX.'news'; }
	public function getOptionsName() { return 'news_options'; }
	public function getColumnDefines()
	{
		return array(
			'news_id' => array(GDO::AUTO_INCREMENT),
			'news_date' => array(GDO::CHAR|GDO::ASCII|GDO::CASE_S, '200912312359', GWF_NEWS_DATE_LEN),
			'news_catid' => array(GDO::INT|GDO::INDEX|GDO::UNSIGNED, 0, 11),
			'news_userid' => array(GDO::OBJECT|GDO::INDEX, 0, array('GWF_User', 'news_userid', 'user_id')),
			'news_readby' => array(GDO::BLOB),
			'news_options' => array(GDO::UINT, 0),
			# Joins
			't2' => array(GDO::JOIN, 0, array('GWF_NewsTranslation', 'news_id', 't2.newst_newsid', '1', 't2.newst_langid')),
			't1' => array(GDO::JOIN, 0, array('GWF_NewsTranslation', 'news_id', 't1.newst_newsid', GWF_Language::getCurrentID(), 't1.newst_langid')),
			'cat' => array(GDO::JOIN, 0, array('GWF_Category', 'news_catid', 'cat_id')),
		);
	}
	public function getID() { return $this->getVar('news_id'); }
	public function getDate() { return $this->getVar('news_date'); }
	public function getCategoryID() { return $this->getVar('news_catid'); }
	public function getUser() { return $this->getVar('news_userid'); }
	public function getCommentsKey() { return '_NEWS_ID_'.$this->getVar('news_id'); }
	
	public function isHidden() { return $this->isOptionEnabled(self::HIDDEN); }
	public function isToBeMailed() { return $this->isOptionEnabled(self::MAIL_ME); }
	public function hasBeenMailed() { return $this->isOptionEnabled(self::MAILED); }
	
	/**
	 * Get a news by ID.
	 * @param $newsid
	 * @return GWF_News
	 */
	public static function getByID($newsid)
	{
		return self::table(__CLASS__)->getRow($newsid);
	}
	
	public static function newNews($date, $catid, $userid, $langid, $title, $message, $fake=false, $options=0)
	{
		$news = new GWF_News(array(
			'news_date' => $date,
			'news_catid' => $catid,
			'news_userid' => $userid,
			'news_readby' => ':',
			'news_options' => $options,
		));
		
		if ($fake === false)
		{
			if (false === ($news->insert())) {
				return false;
			}
		}
		else
		{
			$news->setVar('news_id', '0');
		}
		
		$transdata = array(
			'newst_langid' => $langid,
			'newst_newsid' => $news->getID(),
			'newst_title' => $title,
			'newst_message' => $message,
			'newst_options' => '0',
			'newst_threadid' => '0',
		);

		if ($fake === false)
		{
			$trans = new GWF_NewsTranslation($transdata);
			if (false === ($trans->insert()))
			{
				return false;
			}
		}
		else
		{
// 			$news->setVar('news_trans', array($langid=>$transdata));
			$news->setVar('news_userid', GWF_User::getByID($userid));
			$news->translations = array($transdata);
		}
		
		return $news; 
	}
	
	public static function preview($date, $catid, $userid, $langid, $title, $message)
	{
		return self::newNews($date, $catid, $userid, $langid, $title, $message, true, 0);
	}
	
	public static function getNewsQuick($amount, $catid, $page, $langid)
	{
		$db = gdo_db();
		$fallback = 1;
		$langid = (int)$langid;
		$from = GWF_PageMenu::getFrom($page, $amount);
		$catid = (int)$catid;
		$hidden = self::HIDDEN;
		$catquery = $catid === 0 ? '1' : "news_catid=$catid";
		$permquery = "news_options&$hidden=0";
		$fields = " $langid browser_langid, news_id, IFNULL(t1.newst_title, t2.newst_title) newst_title, IFNULL(t1.newst_message, t2.newst_message) newst_message, user_name, news_date";
		$where = "($catquery) AND ($permquery)";
		$joins = array('t1','t2', 'news_userid');
		return self::table(__CLASS__)->selectAll($fields, $where, 'news_date DESC', $joins, $amount, $from);
	}
	
	public static function getTitlesQuick($catid, $langid)
	{
		$catid = (int)$catid;
		$catquery = $catid === 0 ? '1' : "news_catid=$catid";
		$hidden = self::HIDDEN;
		$permquery = "news_options&$hidden=0";
		$langid = (int)$langid;
		$fallback = 1;
		$fields = "news_id, IFNULL(t1.newst_title,t2.newst_title)";
		$where = "(($catquery) AND ($permquery))";
		$orderby = 'news_date DESC';
		$joins = array('t1','t2');
		return GDO::table(__CLASS__)->selectAll($fields, $where, $orderby, $joins, -1, -1, GDO::ARRAY_N);
	}
	
	public static function getNews($amount, $catid=0, $page=1, $orderby='news_date DESC', $hidden=true)
	{
		$catid = (int) $catid;
		$catid = GWF_Category::categoryExists($catid) ? $catid : 0;
		$catQuery = $catid === 0 ? '1' : "news_catid='$catid'";
		$hiddenQuery = $hidden === true ? '1' : 'news_options&1=0';
		$condition = "($catQuery) AND ($hiddenQuery)";
		$from = GWF_PageMenu::getFrom($page, $amount);
		return GDO::table(__CLASS__)->selectObjects('*', $condition, $orderby, $amount, $from);
	}
	
	/**
	 * @return array
	 */
	public function getFirstTranslation()
	{
		foreach ($this->getTranslations() as $langid => $data)
		{
			return $data;
		}
	}
	
	public function getFirstTitle()
	{
		$t = $this->getFirstTranslation();
		return $t['newst_title'];
	}
	
	public function getFirstMessage()
	{
		$t = $this->getFirstTranslation();
		return $t['newst_message'];
	}
	
	private $chosenTrans = true;
	public function getTranslation()
	{
		if ($this->chosenTrans === true)
		{
			$this->chosenTrans = $this->getTranslationB(GWF_Language::getCurrentID());
		}
		return $this->chosenTrans;
	}
	
	private $translations = NULL;
	public function getTranslationB($__langid)
	{
		if ($this->translations === NULL)
		{
			$id = $this->getID();
			if (false === ($this->translations = GDO::table('GWF_NewsTranslation')->selectAll('newst_langid, newst_title, newst_message', "newst_newsid={$id}", '', NULL, -1, -1, GDO::ARRAY_A)))
			{
				return false;
			}
		}

		$first = true;
		foreach ($this->translations as $data)
		{
			$langid = $data['newst_langid'];
			if ($first === true)
			{
				$first = $data;
			}
			if ($langid == $__langid)
			{
				return $data;
			}
		}
		
		if ($first === true)
		{
			return false;
		}
		
		return $first;
	}
	
	public function displayDate()
	{
		return GWF_Time::displayDate($this->getVar('news_date'));
	}
	
	public function rssDate()
	{
		return GWF_Time::rssDate($this->getVar('news_date'));
	}
	
	public function getTitle()
	{
		if (false === ($translation = $this->getTranslation())) {
			return 'ERR';
		}
		return $translation['newst_title'];
	}
	
	public function displayTitle()
	{
		return GWF_HTML::display($this->getTitle());
	}
	
	public function getTitleL($langid)
	{
		$t = $this->getTranslationB($langid);
		return $t['newst_title'];
	}
	
	public function displayTitleL($langid)
	{
		return GWF_HTML::display($this->getTitleL($langid));
	}
	
	public function getMessage()
	{
		if (false === ($translation = $this->getTranslation()))
		{
			return 'ERR';
		}
		return $translation['newst_message'];
	}
	
	public function displayMessage()
	{
		return GWF_Message::display($this->getMessage());
	}
	
	public function displayAuthor()
	{
		if (false === ($user = ($this->getVar('news_userid'))) 
		|| ($user->getID() === '0')) {
			return GWF_HTML::lang('unknown_user');
		}
		return $user->display('user_name');
	}
	
	public function getCategory()
	{
		if (false === ($cat = GWF_Category::getByID($this->getCategoryID())))
		{
			return false;
		}
		
		$cat->loadTranslations();
		
		return $cat;		
	}
	
	public function getCategoryTitle($langid=0)
	{
		if (false === ($cat = $this->getCategory()))
		{
			return GWF_HTML::lang('no_category');
		}
		return $cat->getTranslation($langid);
	}
	
	public function displayCategory()
	{
		return GWF_HTML::display($this->getCategoryTitle());
	}
	
	public function getTranslations()
	{
		return GDO::table('GWF_NewsTranslation')->selectArrayMap('newst_langid,newst_title,newst_message,news.*,user.*', 'newst_newsid='.$this->getID(), 'newst_langid ASC', array('news','user'));
	}
	
	public function getTranslateSelect()
	{
		$back = '<form method="post" action="'.GWF_WEB_ROOT.'news/edit'.'">';
		$back .= '<div>'.PHP_EOL;
		$back .= GWF_Form::hidden('newsid', $this->getID());
		$back .= '<select name="translate">'.PHP_EOL;
		$langs = GWF_Language::getSupportedLanguages();
		foreach ($langs as $lang)
		{
			$back .= sprintf('<option value="%s">%s</option>', $lang->getID(), $lang->display('lang_nativename')).PHP_EOL;
		}
		$back .= '</select>'.PHP_EOL;
		$back .= '<input type="submit" name="quicktranslate" value="Translate" />'.PHP_EOL;
		$back .= '</div>'.PHP_EOL;
		$back .= '</form>';
		return $back;
	}
	
	public static function getTitles($catid)
	{
		if (false === ($news = self::getNews(0, $catid, 1, 'news_date DESC', false))) {
			return array();
		}
		
		$back = array();
		foreach ($news as $id => $item)
		{
			$back[] = array(
				$item->getTitle(),
				$item->getCategoryID(),
				$item->getCategoryTitle(),
				$item->getID(),
			);
		}
		return $back;
	}
	
	public static function getCategories()
	{
		$langid = GWF_Language::getCurrentID();
		$back = array();
		foreach (GDO::table(__CLASS__)->selectColumn('DISTINCT(`news_catid`)') as $catid)
		{
			if (false === ($cat = GWF_Category::getByID($catid)))
			{
				continue;
			}
			
			$cat->loadTranslations();
			
			$back[] = $cat->getTranslation($langid);
		}
		return $back;
	}
	
	public static function getFakeNews($title, $message)
	{
		return self::newNews(GWF_Time::getDate(), 0, 0, GWF_Language::getCurrentID(), $title, $message, true);
// 		return new GWF_News(array(
// 			'news_date' => GWF_Time::getDate(GWF_Date::LEN_MINUTE),
// 			'news_userid' => false,
// 			'news_trans' => array(
// 				$langid=>array(
// 					'newst_langid' => $langid,
// 					'newst_newsid' => 0,
// 					'newst_title' => $title,
// 					'newst_message' => $message,
// 					'newst_options' => 0,
// 					'newst_threadid' => 0,
// 				),
// 			),
// 		));
	}
	
	/**
	 * 
	 * @param $newsid
	 * @return GWF_News
	 */
	public static function getNewsItem($newsid)
	{
		if (false === ($news = self::table(__CLASS__)->getRow($newsid))) {
			return false;
		}
		$news->loadTranslations();
		return $news;
	}
	
	public function loadTranslations()
	{
		$this->gdo_data['news_trans'] = $this->getTranslations();
	}
	
	public function getNewsletterMessage(Module_News $module, $email)
	{
		if (false === ($nl = GWF_Newsletter::getByEmail($email))) {
			$nl = GWF_Newsletter::getPreviewRow($email);
		}
		
		if (false === ($user = GWF_User::getByEmail($email))) {
			$username = $module->lang('anrede', array( $email));
		}
		else {
			$username = $module->lang('anrede', array( $user->getName()));
		}
		
		$unsign = $nl->getUnsignAnchor();
		
		return $module->lang('newsletter_wrap', array( $username, $unsign, $this->getTitle(), $this->displayMessage()));
	}
	
	public function saveTranslation($langid, $title, $message)
	{
		$langid = (int)$langid;
		$newsid = $this->getID();
		
		$threadid = GDO::table('GWF_NewsTranslation')->selectVar('newst_threadid', "newst_newsid=$newsid AND newst_langid=$langid");
		
		$data = array(
			'newst_langid' => $langid,
			'newst_newsid' => $newsid,
			'newst_title' => $title,
			'newst_message' => $message,
			'newst_threadid' => $threadid,
			'newst_options' => 0,
		);
		
		$trans = new GWF_NewsTranslation($data);
		if (false === ($trans->replace())) {
			return false;
		}
		
		$this->gdo_data['news_trans'][$langid] = $data;

		return true;
	}
	
	public function hrefShow(GWF_Language $lang)
	{
		$langid = $lang->getID();
		$newsid = $this->getVar('news_id');
		$catid = $this->getVar('news_catid');
		$cat = Common::urlencodeSEO($this->getCategoryTitle($langid));
		$title = Common::urlencodeSEO($this->getTitleL($langid));
		return GWF_WEB_ROOT.sprintf('news/%s/%s/%s/%s/%s#newsid_%s', $lang->getVar('lang_iso'), $catid, $cat, $newsid, $title, $newsid);
	}
	
	public function hrefThread($thread_id)
	{
		if (false === ($thread = GWF_ForumThread::getByID($thread_id))) {
			return '#';
		}
		
		return $thread->getLastPageHREF();
	}
	
	public function hrefEdit(GWF_Language $lang)
	{
		return GWF_WEB_ROOT.'news/edit/'.$this->getVar('news_id').'-'.$this->getTitle().'/langid-'.$lang->getVar('lang_id');
	}
	
	public function hrefEnable()
	{
		return GWF_WEB_ROOT.'index.php?mo=News&me=Toggle&hidden=0&newsid='.$this->getID();
	}

	public function hrefDisable()
	{
		return GWF_WEB_ROOT.'index.php?mo=News&me=Toggle&hidden=1&newsid='.$this->getID();
	}
}
?>
