<?php

class BootstrapDataBase extends DataBase {
	protected $forumforums;
	protected $forumtopics;
	protected $forumposts;
	protected $forumcore_tags;
	protected $forummembers;
	protected $forumprofile_portal;
	
	protected $gallery_authors;
	protected $gallery_pictures;

	// const
	protected $cache = [];
	protected $games_by_forum_id = [];
	
	public function __construct($env) {
		parent::__construct($env);

		$this->forumforums = "forumforums";
		$this->forumtopics = "forumtopics";
		$this->forumposts = "forumposts";
		$this->forumcore_tags = "forumcore_tags";
		$this->forummembers = "forummembers";
		$this->forumprofile_portal = "forumprofile_portal";

		$this->gallery_authors = "gallery_authors";
		$this->gallery_pictures = "gallery_pictures";
	}
	
	protected function ExecuteAssoc($query) {
		return $this->Execute($query, true);
	}
	
	protected function ExecuteArrayAssoc($query) {
		return $this->ExecuteArray($query, true);
	}

	// utility
	protected function GetCachedCollection($name, $table, $query_extension = "", $order_by = null) {
		if (!isset($this->cache[$name])) {
			$query = "SELECT *{$query_extension} FROM {$table}";
			if ($order_by != null) {
				$query .= " order by {$order_by}";
			}

			$this->cache[$name] = $this->ExecuteAssoc($query);
		}

		return $this->cache[$name];
	}

	protected function GetEntityByField($collection, $field_name, $value) {
		foreach ($collection as $entity) {
			if ($entity[$field_name] == $value) {
				return $entity;
			}
		}
	}

	protected function GetEntity($collection, $id) {
		return $this->GetEntityByField($collection, 'id', $id);
	}
	
	protected function GetEntityById($table, $id) {
		$query = "select * from {$table} where id = {$id}";
		return $this->ExecuteArrayAssoc($query);
	}

	// getters
	protected function GetGameForumsFilter($game, $prefix = null) {
		$filter = "";
		if ($game != null) {
			$forums = $this->GetForumsByGameId($game['id']);
			if (count($forums) > 0) {
				foreach ($forums as $forum) {
					$forum_ids[] = $forum['id'];
				}
				
				$filter = " and {$prefix}forum_id in (".implode(",", $forum_ids).")";
			}
		}
		
		return $filter;
	}
	
	public function GetLatestNews($limit, $game = null, $except_news_id = null) {
		$forums_filter = $this->GetGameForumsFilter($game, "ft.");
		$news_filter = ($except_news_id != null) ? " and ft.tid <> {$except_news_id}" : "";

		$query = "SELECT ft.*, fp.post FROM {$this->forumtopics} ft inner join {$this->forumposts} fp on fp.topic_id = ft.tid WHERE ft.forum_id IN (select news_forum_id from {$this->warcry_game}){$forums_filter}{$news_filter} and fp.new_topic = 1 ORDER BY ft.start_date DESC LIMIT {$limit}";

		return $this->ExecuteAssoc($query);
	}

	public function GetNews($id) {
		$query = "SELECT ft.*, fp.post FROM {$this->forumtopics} ft inner join {$this->forumposts} fp on fp.topic_id = ft.tid WHERE ft.tid = {$id} and ft.forum_id IN (select news_forum_id from {$this->warcry_game})";

		return $this->ExecuteArrayAssoc($query);
	}

	public function GetForumTopicTags($topic_id) {
		$query = "select tag_text from {$this->forumcore_tags} where tag_meta_app = 'forums' and tag_meta_area = 'topics' and tag_meta_id = {$topic_id}";

		return $this->ExecuteAssoc($query);
	}

	public function GetLatestArticles($limit, $game = null, $except_article_id = null) {
		$game_filter = ($game != null) ? " and ed.game_id = {$game['id']}" : "";
		$article_filter = ($except_article_id != null) ? " and ed.id <> {$except_article_id}" : "";
		
		$query = "SELECT ed.name_en, ed.name_ru, ec.name_en 'cat', wg.alias 'game_alias', wg.name 'game_name' FROM {$this->table_data} ed
LEFT JOIN {$this->table_cat} ec ON ec.id = ed.cat
INNER JOIN {$this->warcry_game} wg ON wg.id = ed.game_id
WHERE ed.published = 1 and ed.announce = 1{$game_filter}{$article_filter}
ORDER BY ed.created_at DESC
LIMIT {$limit}";

		return $this->ExecuteAssoc($query);
	}

	public function GetLatestForumTopics($limit, $game = null) {
		$forums_filter = $this->GetGameForumsFilter($game);
		
		$query = "SELECT * FROM {$this->forumtopics}
WHERE not forum_id in ({$this->env->settings->hidden_forum_ids}){$forums_filter}
AND state <> 'link'
AND posts > 0

ORDER BY last_post DESC
LIMIT {$limit}";

		return $this->ExecuteAssoc($query);
	}

	public function GetGames() {
		return $this->GetCachedCollection('games', $this->warcry_game, ", id = {$this->env->settings->default_game_id} as 'default'", 'position');
	}

	public function GetGame($id) {
		return $this->GetEntity($this->GetGames(), $id);
	}

	public function GetDefaultGame() {
		return $this->GetGame($this->env->settings->default_game_id);
	}

	public function GetForums() {
		return $this->GetCachedCollection('forums', $this->forumforums);
	}

	public function GetForum($id) {
		return $this->GetEntity($this->GetForums(), $id);
	}

	public function GetGameByForumId($forum_id) {
		if (!isset($this->games_by_forum_id[$forum_id])) {
			$games = $this->GetGames();
			$found_game = null;

			$cur_forum_id = $forum_id;
			
			while ($found_game == null && $cur_forum_id != -1) {
				foreach ($games as $game) {
					if ($game['news_forum_id'] == $cur_forum_id || $game['main_forum_id'] == $cur_forum_id) {
						$found_game = $game;
						break;
					}
				}

				if ($found_game == null) {
					$forum = $this->GetForum($cur_forum_id);
					$cur_forum_id = $forum['parent_id'];
				}
			}

			if ($found_game == null) {
				$found_game = $this->GetDefaultGame();
			}
			
			$this->games_by_forum_id[$forum_id] = $found_game;
		}

		return $this->games_by_forum_id[$forum_id];
	}
	
	public function GetForumsByGameId($game_id) {
		foreach ($this->GetForums() as $forum) {
			$game = $this->GetGameByForumId($forum['id']);
			if ($game['id'] == $game_id) {
				$result[] = $forum;
			}
		}
		
		return $result;
	}

	public function GetMenuSections($game_id) {
		$query = "select * from {$this->wow_menu_sections} where game_id = {$game_id} order by position";

		return $this->ExecuteAssoc($query);
	}

	public function GetMenuItems($menu_id) {
		$query = "select * from {$this->wow_menu_items} where section_id = {$menu_id} order by position";

		return $this->ExecuteAssoc($query);
	}
	
	protected function GetGalleryAuthorsQuery($author_id = null) {
		$where = ($author_id > 0) ? " and ga.id = {$author_id}" : "";

		$query = "SELECT ga.*, fm.member_id, count(*) as count, pp_main_photo as avatar, pp_main_width as avatar_x, pp_main_height as avatar_y
FROM {$this->gallery_pictures} gp
INNER JOIN {$this->gallery_authors} ga ON gp.author_id = ga.id
left join {$this->forummembers} fm on fm.name = ga.name
left join {$this->forumprofile_portal} fpp on fpp.pp_member_id = fm.member_id
where gp.published = 1{$where}
GROUP BY ga.id
ORDER BY count DESC";

		return $query;
	}
	
	public function GetGalleryAuthors() {
		$query = $this->GetGalleryAuthorsQuery();
		return $this->ExecuteAssoc($query);
	}
	
	public function GetGalleryAuthor($author_id) {
		$query = $this->GetGalleryAuthorsQuery($author_id);
		return $this->ExecuteArrayAssoc($query);
	}
	
	public function GetGalleryAuthorIdByAlias($alias) {
		$alias = $this->Escape($alias);
		$query = "select id from {$this->gallery_authors} where alias = '{$alias}'";
		return $this->ExecuteScalar($query);
	}
	
	public function GetGalleryPictures($author_id, $offset = 0, $limit = 24) {
		$query = "SELECT id, comment, picture_type, thumb_type FROM {$this->gallery_pictures} WHERE author_id = {$author_id} and published = 1 ORDER BY created_at DESC LIMIT {$offset}, {$limit}";
		return $this->ExecuteAssoc($query);
	}
	
	public function GetGalleryPicture($id) {
		$query = "select id, author_id, comment, date_format(created_at, '%d.%m.%Y') as created_at, date_format(updated_at, '%d.%m.%Y %H:%i:%s') as updated_at, official, description, picture_type, thumb_type from {$this->gallery_pictures} where id = {$id} and published = 1";

		return $this->ExecuteArrayAssoc($query);
	}
	
	public function GetGalleryPicturePrev($id) {
		$query = "select pic1.id, pic1.comment from {$this->gallery_pictures} pic1
			inner join {$this->gallery_pictures} pic2 on pic1.author_id = pic2.author_id and pic1.created_at > pic2.created_at where pic2.id = {$id} and pic2.published = 1 order by pic1.created_at limit 1";

		return $this->ExecuteArrayAssoc($query);
	}
	
	public function GetGalleryPictureNext($id) {
		$query = "select pic1.id, pic1.comment from {$this->gallery_pictures} pic1
			inner join {$this->gallery_pictures} pic2 on pic1.author_id = pic2.author_id and pic1.created_at < pic2.created_at where pic2.id = {$id} and pic2.published = 1 order by pic1.created_at desc limit 1";

		return $this->ExecuteArrayAssoc($query);
	}
	
	public function GetUser($id) {
		$query = "SELECT u.*, fm.member_id from {$this->users} u
left join {$this->forummembers} fm on fm.name = coalesce(u.forum_name, u.login)
where u.id = {$id}";

		return $this->ExecuteArrayAssoc($query);
	}
}