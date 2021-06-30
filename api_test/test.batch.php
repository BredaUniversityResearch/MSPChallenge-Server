<?php

class TestBatch extends TestBase
{
	public function __construct(string $token)
	{
		parent::__construct($token);
	}

	/**
	 * @TestMethod
	 */
	public function StartBatch()
	{
		$response = $this->DoApiRequest("/api/batch/StartBatch", array());
		Assert::ExpectIntValue($response);
	}

	/**
	 * @TestMethod
	 */
	public function AddToBatch()
	{
		$batchId = $this->DoApiRequest("/api/batch/StartBatch", array());
		$response = $this->DoApiRequest("/api/batch/AddToBatch", array("batch_id" => $batchId, "batch_group" => 0, "call_id"=> null, "endpoint"=>"api/geometry/post", "endpoint_data"=>json_encode(array())));
		Assert::ExpectStringValue($response);
	}

	/**
	 * @TestMethod
	 */
	public function ExecuteBatch()
	{
		$batchId = $this->DoApiRequest("/api/batch/StartBatch", array());
		$executeId = $this->DoApiRequest("/api/batch/AddToBatch", array("batch_id" => $batchId, 
			"batch_group" => 0, 
			"call_id"=> "CurrentMonth", 
			"endpoint"=>"api/game/GetCurrentMonth", 
			"endpoint_data"=>json_encode(array())
		));

		$executeId = $this->DoApiRequest("/api/batch/AddToBatch", array("batch_id" => $batchId, 
			"batch_group" => 0, 
			"call_id"=> "ActualDate", 
			"endpoint"=>"api/game/GetActualDateForSimulatedMonth", 
			"endpoint_data"=>json_encode(array("simulated_month" => 10))
		));

		$executeId = $this->DoApiRequest("/api/batch/AddToBatch", array("batch_id" => $batchId, 
			"batch_group" => 0, 
			"call_id"=> "ActualDate", 
			"endpoint"=>"api/energy/AddGrid", 
			"endpoint_data"=>'{
				"name": "Green 5",
				"plan": 5,
				"distribution_only": "false",
				"persistent": 21
			  }'
		));

		$result = $this->DoApiRequest("/api/batch/ExecuteBatch", array("batch_id" => $batchId));
		Assert::ExpectArrayValues($result, Assert::CONSTRAINT_ARRAY, ["results"]);
		Assert::ExpectStringValue($result["results"][0]["call_id"], "CurrentMonth");
		Assert::ExpectArrayValues($result["results"][0]["payload"], Assert::CONSTRAINT_INT, ["game_currentmonth"]);
		Assert::ExpectStringValue($result["results"][1]["call_id"], "ActualDate");
		Assert::ExpectArrayValues($result["results"][1]["payload"], Assert::CONSTRAINT_INT, ["year", "month_of_year"]);
	}

	/**
	 * @TestMethod
	 */
	public function ExecuteBatchReferenced()
	{
		$batchId = $this->DoApiRequest("/api/batch/StartBatch", array());
		$this->DoApiRequest("/api/batch/AddToBatch", array(
			"batch_id" => $batchId, 
			"batch_group" => 0, 
			"call_id"=> "PlanCreate", 
			"endpoint"=>"api/plan/post", 
			"endpoint_data"=>json_encode(array(
				"country" => 3, 
				"name" => "Batch Test Plan #1", 
				"time" => 120,
				"layers" => [],
				"type" => "0,0,0",
				"alters_energy_distribution" => false))
		));
		$this->DoApiRequest("/api/batch/AddToBatch", array(
			"batch_id" => $batchId, 
			"batch_group" => 1, 
			"call_id"=> "PlanLayer", 
			"endpoint"=>"api/plan/layer", 
			"endpoint_data"=>json_encode(array(
				"id" => "!Ref:PlanCreate", 
				"layerid" => 2))
		));
		$this->DoApiRequest("/api/batch/AddToBatch", array(
			"batch_id" => $batchId, 
			"batch_group" => 2, 
			"call_id"=> "GeometryCreate0", 
			"endpoint"=>"api/geometry/post", 
			"endpoint_data"=>json_encode(array(
				"country" => 3, 
				"plan" => "!Ref:PlanCreate", 
				"layer" => "!Ref:PlanLayer",
				"geometry" => "[[3488833.0042704,3924473.5412358]]"))
		));
		$this->DoApiRequest("/api/batch/AddToBatch", array(
			"batch_id" => $batchId, 
			"batch_group" => 3, 
			"call_id"=> "GeometryCreate1", 
			"endpoint"=>"api/geometry/post", 
			"endpoint_data"=>json_encode(array(
				"country" => 3, 
				"plan" => "!Ref:PlanCreate", 
				"layer" => "!Ref:PlanLayer",
				"geometry" => "[[3488833.0042704,3924473.5412358]]"))
		));
		$result = $this->DoApiRequest("/api/batch/ExecuteBatch", array("batch_id" => $batchId));

		Assert::ExpectArrayValues($result, Assert::CONSTRAINT_ARRAY, ["results"]);
		Assert::ExpectStringValue($result["results"][0]["call_id"], "PlanCreate");
		Assert::ExpectStringValue($result["results"][1]["call_id"], "PlanLayer");
		Assert::ExpectStringValue($result["results"][2]["call_id"], "GeometryCreate0");
		Assert::ExpectStringValue($result["results"][3]["call_id"], "GeometryCreate1");

		Assert::ExpectIntValue($result["results"][0]["payload"]);
		Assert::ExpectIntValue($result["results"][1]["payload"]);
		Assert::ExpectIntValue($result["results"][2]["payload"]);
		Assert::ExpectIntValue($result["results"][3]["payload"]);
	}

	/**
	 * @TestMethod
	 */
	public function ExecuteBatchMultiReferenced()
	{
		$batchId = $this->DoApiRequest("/api/batch/StartBatch", array());
		$this->DoApiRequest("/api/batch/AddToBatch", array(
			"batch_id" => $batchId, 
			"batch_group" => 0, 
			"call_id"=> "PlanCreate", 
			"endpoint"=>"api/plan/post", 
			"endpoint_data"=>json_encode(array(
				"country" => 3, 
				"name" => "Batch Test Plan #2", 
				"time" => 120,
				"layers" => [],
				"type" => "0,0,0",
				"alters_energy_distribution" => false))
		));
		$this->DoApiRequest("/api/batch/AddToBatch", array(
			"batch_id" => $batchId, 
			"batch_group" => 1, 
			"call_id"=> "PlanLayer", 
			"endpoint"=>"api/plan/layer", 
			"endpoint_data"=>json_encode(array(
				"id" => "!Ref:PlanCreate", 
				"layerid" => 2))
		));
		$this->DoApiRequest("/api/batch/AddToBatch", array(
			"batch_id" => $batchId, 
			"batch_group" => 4, 
			"call_id"=> "AddGrid1", 
			"endpoint"=>"api/energy/AddGrid", 
			"endpoint_data"=>json_encode(array(
				"name" => "TestGrid", 
				"plan" => "!Ref:PlanCreate", 
				"distribution_only" => "false"))
		));
		$this->DoApiRequest("/api/batch/AddToBatch", array(
			"batch_id" => $batchId, 
			"batch_group" => 5, 
			"call_id"=> "UpdateGridSources1", 
			"endpoint"=>"api/energy/UpdateGridSources", 
			"endpoint_data"=>json_encode(array(
				"id" => "!Ref:AddGrid1", 
				"sources" => ["!Ref:GeometryCreate0", "!Ref:GeometryCreate1"]))
		));
		$this->DoApiRequest("/api/batch/AddToBatch", array(
			"batch_id" => $batchId, 
			"batch_group" => 2, 
			"call_id"=> "GeometryCreate0", 
			"endpoint"=>"api/geometry/post", 
			"endpoint_data"=>json_encode(array(
				"country" => 3, 
				"plan" => "!Ref:PlanCreate", 
				"layer" => "!Ref:PlanLayer",
				"geometry" => "[[3488833.0042704,3924473.5412358]]"))
		));
		$this->DoApiRequest("/api/batch/AddToBatch", array(
			"batch_id" => $batchId, 
			"batch_group" => 3, 
			"call_id"=> "GeometryCreate1", 
			"endpoint"=>"api/geometry/post", 
			"endpoint_data"=>json_encode(array(
				"country" => 3, 
				"plan" => "!Ref:PlanCreate", 
				"layer" => "!Ref:PlanLayer",
				"geometry" => "[[3488833.0042704,3924473.5412358]]"))
		));
		$result = $this->DoApiRequest("/api/batch/ExecuteBatch", array("batch_id" => $batchId));
		
		Assert::ExpectArrayValues($result, Assert::CONSTRAINT_ARRAY, ["results"]);
		Assert::ExpectStringValue($result["results"][0]["call_id"], "PlanCreate");
		Assert::ExpectStringValue($result["results"][1]["call_id"], "PlanLayer");
		Assert::ExpectStringValue($result["results"][2]["call_id"], "GeometryCreate0");
		Assert::ExpectStringValue($result["results"][3]["call_id"], "GeometryCreate1");
		Assert::ExpectStringValue($result["results"][4]["call_id"], "AddGrid1");
		Assert::ExpectStringValue($result["results"][5]["call_id"], "UpdateGridSources1");

		Assert::ExpectIntValue($result["results"][0]["payload"]);
		Assert::ExpectIntValue($result["results"][1]["payload"]);
		Assert::ExpectIntValue($result["results"][2]["payload"]);
		Assert::ExpectIntValue($result["results"][3]["payload"]);
		Assert::ExpectIntValue($result["results"][4]["payload"]);
	}
}

?>