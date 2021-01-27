<?php
	class Geometry extends Base 
	{

		protected $allowed = array(
			"Post",
			"PostSubtractive", 
			"Update", 
			"Data",  
			"Delete", 
			"MarkForDelete", 
			"UnmarkForDelete"
		);

		/**
		 * @apiGroup Geometry
		 * @api {post} /geometry/post Post
		 * @apiDescription Create a new geometry entry in a plan
		 * @apiParam {int} layer id of layer to post in
		 * @apiParam {string} geometry string of geometry to post
		 * @apiParam {int} plan id of the plan
		 * @apiParam {string} FID (optional) FID of geometry
		 * @apiParam {int} persistent (optional) persistent ID of geometry
		 * @apiParam {string} data (optional) meta data string of geometry object
		 * @apiParam {int} country (optional) The owning country id. NULL or -1 if no country is set. 
		 * @apiSuccess {int} id of the newly created geometry
		 */
		public function Post(string $FID = "", int $persistent = null, string $data = "", int $country = null, int $plan = -1, int $layer, string $geometry)
		{
			$newid = $this->query("INSERT INTO geometry (geometry_layer_id, geometry_geometry, geometry_FID, geometry_persistent, geometry_data, geometry_country_id) 
									VALUES (?, ?, ?, ?, ?, ?)", array($layer, $geometry, $FID, $persistent, $data, $country), true);

			if($plan != -1){
				$this->query("UPDATE plan SET plan_lastupdate=? WHERE plan_id=?", array(microtime(true), $plan));
			}

			//set the persistent id if it's new geometry
			if(is_null($persistent)){
				$this->query("UPDATE geometry SET geometry_persistent=? WHERE geometry_id=?", array($newid, $newid));
			}

			return $newid;
		}

		/**
		 * @apiGroup Geometry
		 * @api {POST} /geometry/PostSubtractive Post Subtractive
		 * @apiDescription Create a new subtractive polygon on an existing polygon
		 * @apiParam {int} layer id of layer to post in
		 * @apiParam {string} geometry string of geometry to post
		 * @apiParam {int} subtractive id of the polygon the newly created polygon is subtractive to
		 * @apiParam {string} FID (optional) FID of geometry
		 * @apiParam {string} persistent (optional) persistent ID of geometry
		 */
		public function PostSubtractive(int $persistent = null, string $FID = "", int $layer, string $geometry, int $subtractive)
		{
			$data = $this->query("INSERT INTO geometry 
				(geometry_layer_id, geometry_geometry, geometry_FID, geometry_persistent, geometry_subtractive) 
				VALUES (?, ?, ?, ?, ?)", 
				array($layer, $geometry, $FID, $persistent, $subtractive), true);

			//set the persistent id if it's new geometry
			if(is_null($persistent)){
				$this->query("UPDATE geometry SET geometry_persistent=? WHERE geometry_id=?", array($data, $data));
			}
		}
		
		/**
		 * @apiGroup Geometry
		 * @api {POST} /geometry/update Update
		 * @apiParam {int} id geometry id to update
		 * @apiParam {string} geometry string of geometry json to post
		 * @apiParam {int} country country id to set as geometry's owner
		 * @apiSuccess {int} id same geometry id
		 */
		public function Update(int $id, int $country, string $geometry)
		{
			$this->query("UPDATE geometry SET geometry_geometry = ?, geometry_country_id = ? WHERE geometry_id = ?",
									array($geometry, $country, $id));
			return $id;
		}

		/**
		 * @apiGroup Geometry
		 * @api {POST} /geometry/Data Data
		 * @apiDescription Adjust geometry metadata and type
		 * @apiParam {int} id geometry id to update
		 * @apiParam {string} data metadata of the geometry to set
		 * @apiParam {string} type type value, either single integer or comma-separated multiple integers
		 */
		public function Data(string $data, string $type, int $id)
		{
			$this->query("UPDATE geometry SET geometry_data=?, geometry_type=? WHERE geometry_id=?", array($data, $type, $id));
		}

		/**
		 * @apiGroup Geometry
		 * @api {POST} /geometry/Delete Delete
		 * @apiDescription Delete geometry without using a plan
		 * @apiParam {int} id geometry id to delete, marks a row as inactive
		 */
		public function Delete(int $id)
		{
			$this->query("UPDATE geometry SET geometry_active = 0, geometry_deleted = 1 WHERE (geometry_id=? OR geometry_subtractive=?)", array($id, $id));
		}

		/**
		 * @apiGroup Geometry
		 * @api {POST} /geometry/MarkForDelete MarkForDelete
		 * @apiDescription Delete geometry using a plan, this will be triggered at the execution time of a plan
		 * @apiParam {int} id geometry persistent id to delete
		 * @apiParam {int} plan plan id where the geometry will be deleted
		 * @apiParam {int} layer the layer id where the geometry will be deleted
		 */
		public function MarkForDelete(int $plan, int $id, int $layer)
		{
			$this->query("INSERT INTO plan_delete (plan_delete_plan_id, plan_delete_geometry_persistent, plan_delete_layer_id) VALUES (?, ?, ?)", 
				array($plan, $id, $layer)
			);
		}

		/**
		 * @apiGroup Geometry
		 * @api {POST} /geometry/UnmarkForDelete UnmarkForDelete
		 * @apiDescription Remove the deletion of a geometry put in the plan
		 * @apiParam {int} id geometry persistent id to undelete
		 * @apiParam {int} plan plan id where the geometry is located in
		 */
		public function UnmarkForDelete(int $plan, int $id)
		{
			$this->query("DELETE FROM plan_delete WHERE plan_delete_plan_id=? AND plan_delete_geometry_persistent=?", 
				array($plan, $id)
			);
		}
		
	}
?>