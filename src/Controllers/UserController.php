<?php
class UserController extends AbstractController
{
	public static function getUserRegex()
	{
		return '[0-9a-zA-Z_-]{2,}';
	}

	public static function parseRequest($url, &$controllerContext)
	{
		$userRegex = self::getUserRegex();
		$modulesRegex = self::getAvailableModulesRegex();
		$mediaParts = array_map(['Media', 'toString'], Media::getConstList());
		$mediaRegex = implode('|', $mediaParts);

		$regex =
			'^/?' .
			'(' . $userRegex . ')' .
			'(' . $modulesRegex . ')' .
			'(,(' . $mediaRegex . '))?' .
			'/?($|\?)';

		if (!preg_match('#' . $regex . '#', $url, $matches))
		{
			return false;
		}

		$controllerContext->userName = $matches[1];
		$media = !empty($matches[4]) ? $matches[4] : 'anime';
		switch ($media)
		{
			case 'anime': $controllerContext->media = Media::Anime; break;
			case 'manga': $controllerContext->media = Media::Manga; break;
			default: throw new BadMediaException();
		}
		$rawModule = ltrim($matches[2], '/') ?: 'profile';
		$controllerContext->rawModule = $rawModule;
		$controllerContext->module = self::getModuleByUrlPart($rawModule);
		assert(!empty($controllerContext->module));
		return true;
	}

	public static function work($controllerContext, &$viewContext)
	{
		$viewContext->userName = $controllerContext->userName;
		$viewContext->media = $controllerContext->media;
		$viewContext->module = $controllerContext->module;
		$viewContext->meta->styles []= '/media/css/menu.css';
		$viewContext->meta->styles []= '/media/css/user/general.css';

		if (BanHelper::isBanned($viewContext->userName))
		{
			$viewContext->meta->styles []= '/media/css/narrow.css';
			$viewContext->viewName = 'error-user-blocked';
			return;
		}

		$queue = new Queue(Config::$userQueuePath);
		$queue->enqueue($controllerContext->userName);

		$pdo = Database::getPDO();
		$stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(name) = LOWER(?)');
		$stmt->Execute([$viewContext->userName]);
		$result = $stmt->fetch();
		if (empty($result))
		{
			$viewContext->meta->styles []= '/media/css/narrow.css';
			$viewContext->viewName = 'error-user-enqueued';
			return;
		}
		$viewContext->userId = $result->user_id;
		$viewContext->userPictureUrl = $result->picture_url;

		assert(!empty($controllerContext->module));
		$module = $controllerContext->module;
		$module::work($viewContext);
		$viewContext->userMenu = true;
	}
}
