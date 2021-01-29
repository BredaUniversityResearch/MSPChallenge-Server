<?php
class Warning extends Base {
	
	protected $allowed = array(
		"Post", 
		"Update",
		"SetShippingIssues"
	);
	
	public function __construct($method = "")
	{
		parent::__construct($method);
	}

	/**
	 * @apiGroup Warning
	 * @api {POST} /warning/post Post
	 * @apiParam {added} Json array of IssueObjects that are added.
	 * @apiParams {removed} Json array of IssueObjects that are removed.
	 * @apiDescription Add or update a warning message on the server
	 */
	public function Post(array $added, array $removed)
	{
		foreach($added as $addedIssue) {
			$existingIssue = Database::GetInstance()->query("SELECT warning_id FROM warning WHERE warning_active = 1 AND warning_layer_id = ? AND warning_issue_type = ? AND abs(warning_x - ?) <= 1e-6 AND abs(warning_y - ?) <= 1e-6 AND warning_source_plan_id = ? AND warning_restriction_id = ?", 
				array($addedIssue['plan_layer_id'], $addedIssue['type'], $addedIssue['x'], $addedIssue['y'], $addedIssue['source_plan_id'], $addedIssue['restriction_id']));
			
			if (count($existingIssue) == 0) {
				Database::GetInstance()->query("INSERT INTO warning (warning_last_update, warning_active, warning_layer_id, warning_issue_type, warning_x, warning_y, warning_source_plan_id, warning_restriction_id)
					VALUES(?, 1, ?, ?, ?, ?, ?, ?)", array(microtime(true), $addedIssue['plan_layer_id'], $addedIssue['type'], $addedIssue['x'], $addedIssue['y'], $addedIssue['source_plan_id'], $addedIssue['restriction_id']));
			}
			else {
				Database::GetInstance()->query("UPDATE warning SET warning_last_update = ?, warning_active = 1 WHERE warning_id = ?", array(microtime(true), $existingIssue[0]["warning_id"]));
				if (count($existingIssue) > 1) {
					//Something has already gone horribly wrong, try to save it by disabling all the other issues that we found that match our data.
					for ($i = 1; $i < count($existingIssue); ++$i) {
						Database::GetInstance()->query("UPDATE warning SET warning_last_update = ?, warning_active = 0 WHERE warning_id = ?", array(microtime(true), $existingIssue[$i]["warning_id"]));
					}
				}
			}
		}

		foreach($removed as $removedIssue) {
			Database::GetInstance()->query("UPDATE warning SET warning_active = 0, warning_last_update = ? WHERE warning_id = ?", array(microtime(true), $removedIssue['issue_database_id']));
		}
	}

	public function RemoveAllWarningsForLayer($layerId)
	{
		Database::GetInstance()->query("UPDATE warning SET warning_active = 0, warning_last_update = ? WHERE warning_source_plan_id = ?", array(microtime(true), $layerId));
	}
	
	/** 
	 * @apiGroup Warning
	 * @api {POST} /warning/SetShippingIssues Set shipping issues
	 * @apiParam {string} issues The JSON encoded issues of SEL.APIShippingIssue type. 
	 * @apiDescription Clears out the old shipping issues and creates new shipping issues defined by issues
	 */
	public function SetShippingIssues(string $issues)
	{
		Database::GetInstance()->query("UPDATE shipping_warning SET shipping_warning_active = 0, shipping_warning_lastupdate = ? WHERE shipping_warning_active = 1", array(microtime(true)));

		$newIssues = json_decode($issues, true);
		foreach($newIssues as $issue)
		{
			Database::GetInstance()->query("INSERT INTO shipping_warning (shipping_warning_lastupdate, shipping_warning_source_geometry_persistent_id, shipping_warning_destination_geometry_persistent_id, shipping_warning_message) 
				VALUES(?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE shipping_warning_active = 1, shipping_warning_lastupdate = ?", 
				array(microtime(true), $issue['source_geometry_persistent_id'], $issue['destination_geometry_persistent_id'], $issue['message'], microtime(true)));
		}	
	}

	public function Latest($time){
		$result = array();
		$result['plan_issues'] = Database::GetInstance()->query("SELECT 
				warning_id as issue_database_id,
				warning_active as active,
				warning_layer_id as plan_layer_id,
				warning_issue_type as type,
				warning_x as x,
				warning_y as y,
				warning_source_plan_id as source_plan_id,
				warning_restriction_id as restriction_id
			FROM warning WHERE warning_last_update>?", array($time));

		$result['shipping_issues'] = Database::GetInstance()->query("SELECT 
				shipping_warning_id as warning_id,
				shipping_warning_source_geometry_persistent_id as source_geometry_persistent_id,
				shipping_warning_destination_geometry_persistent_id as destination_geometry_persistent_id,
				shipping_warning_message as message,
				shipping_warning_active as active
			FROM shipping_warning
			WHERE shipping_warning_lastupdate > ?", array($time));

		return $result;
	}
}
?>