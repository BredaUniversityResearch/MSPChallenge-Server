define({ "api": [
  {
    "group": "Batch",
    "type": "POST",
    "url": "/batch/addtobatch",
    "title": "AddToBatch",
    "description": "<p>Starts a new batch</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "batch_id",
            "description": "<p>Batch ID to add to</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "batch_group",
            "description": "<p>Batch execution group</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "call_id",
            "description": "<p>Client defined identfier that is unique within the current batch_id.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "endpoint",
            "description": "<p>Endpoint for this batch call to make. E.g. &quot;api/geometry/post&quot;</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "endpoint_data",
            "description": "<p>Json data that should be send to the endpoint when called.</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "int",
            "optional": false,
            "field": "execution_task_id",
            "description": "<p>Unique task identifier used to identify this execution task.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.batch.php",
    "groupTitle": "Batch",
    "name": "PostBatchAddtobatch",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/batch/addtobatch"
      }
    ]
  },
  {
    "group": "Batch",
    "type": "POST",
    "url": "/batch/addtobatch",
    "title": "AddToBatch",
    "description": "<p>Starts a new batch</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "batch_id",
            "description": "<p>Batch ID to execute.</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "object",
            "optional": false,
            "field": "results",
            "description": "<p>Results of the executed batch. JSON object containing client-specified call_id (if non-empty) and payload which is the result of the call. When failed this object contains failed_task_id which references the execution_task_id returned in the AddToBatch.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.batch.php",
    "groupTitle": "Batch",
    "name": "PostBatchAddtobatch",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/batch/addtobatch"
      }
    ]
  },
  {
    "group": "Batch",
    "type": "POST",
    "url": "/batch/startbatch",
    "title": "StartBatch",
    "description": "<p>Starts a new batch</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "int",
            "optional": false,
            "field": "batch_id",
            "description": "<p>Batch ID used to identify this batch.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.batch.php",
    "groupTitle": "Batch",
    "name": "PostBatchStartbatch",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/batch/startbatch"
      }
    ]
  },
  {
    "group": "Cel",
    "type": "POST",
    "url": "/cel/GetCELConfig",
    "title": "Get Config",
    "description": "<p>Returns the Json encoded config string</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.cel.php",
    "groupTitle": "Cel",
    "name": "PostCelGetcelconfig",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/cel/GetCELConfig"
      }
    ]
  },
  {
    "group": "Cel",
    "type": "POST",
    "url": "/cel/GetConnections",
    "title": "GetConnections",
    "description": "<p>Get all active energy connections</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.cel.php",
    "groupTitle": "Cel",
    "name": "PostCelGetconnections",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/cel/GetConnections"
      }
    ]
  },
  {
    "group": "Cel",
    "type": "POST",
    "url": "/cel/GetConnections",
    "title": "GetConnections",
    "description": "<p>Get all active energy connections</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Cel",
    "name": "PostCelGetconnections",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/cel/GetConnections"
      }
    ]
  },
  {
    "group": "Cel",
    "type": "POST",
    "url": "/cel/GetGrids",
    "title": "Get Grids",
    "description": "<p>Get all grids and their associated sockets, sorted per country</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.cel.php",
    "groupTitle": "Cel",
    "name": "PostCelGetgrids",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/cel/GetGrids"
      }
    ]
  },
  {
    "group": "Cel",
    "type": "POST",
    "url": "/cel/GetNodes",
    "title": "Get Nodes",
    "description": "<p>Get all nodes that have an output associated with them</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.cel.php",
    "groupTitle": "Cel",
    "name": "PostCelGetnodes",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/cel/GetNodes"
      }
    ]
  },
  {
    "group": "Cel",
    "type": "POST",
    "url": "/cel/GetSources",
    "title": "Get Sources",
    "description": "<p>Returns a list of all active sources</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.cel.php",
    "groupTitle": "Cel",
    "name": "PostCelGetsources",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/cel/GetSources"
      }
    ]
  },
  {
    "group": "Cel",
    "type": "POST",
    "url": "/cel/SetGeomCapacity",
    "title": "Set Geometry Capacity",
    "description": "<p>Set the energy capacity of a specific geometry object</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "geomCapacityValues",
            "description": "<p>Json Encoded string in the format [ { &quot;id&quot; : GRID_ID, &quot;capacity&quot;: CAPACITY_VALUE }]</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "capacity",
            "description": "<p>capacity of node</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.cel.php",
    "groupTitle": "Cel",
    "name": "PostCelSetgeomcapacity",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/cel/SetGeomCapacity"
      }
    ]
  },
  {
    "group": "Cel",
    "type": "POST",
    "url": "/cel/SetGridCapacity",
    "title": "Set Grid Capacity",
    "description": "<p>Set the energy capacity of a grid per country, uses the server month time</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "kpiValues",
            "description": "<p>Json encoded string in the format [ { &quot;grid&quot;: GRID_ID, &quot;actual&quot;: ACTUAL_ENERGY_VALUE, &quot;country&quot;: COUNTRY_ID } ]</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.cel.php",
    "groupTitle": "Cel",
    "name": "PostCelSetgridcapacity",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/cel/SetGridCapacity"
      }
    ]
  },
  {
    "group": "Cel",
    "type": "POST",
    "url": "/cel/ShouldUpdate",
    "title": "Should Update",
    "description": "<p>Should Cel update this month?</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.cel.php",
    "groupTitle": "Cel",
    "name": "PostCelShouldupdate",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/cel/ShouldUpdate"
      }
    ]
  },
  {
    "group": "Cel",
    "type": "POST",
    "url": "/cel/UpdateFinished",
    "title": "Update Finished",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "month",
            "description": "<p>The month Cel just finished an update for.</p>"
          }
        ]
      }
    },
    "description": "<p>Notify that Cel has finished updating a month</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.cel.php",
    "groupTitle": "Cel",
    "name": "PostCelUpdatefinished",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/cel/UpdateFinished"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/AddGrid",
    "title": "Add Grid",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "name",
            "description": "<p>grid name</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>plan id</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "distribution_only",
            "description": "<p>...</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "persistent",
            "description": "<p>(optional) persistent id, defaults to the newly created id</p>"
          }
        ]
      }
    },
    "description": "<p>Add a new grid</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "int",
            "optional": false,
            "field": "success",
            "description": "<p>grid id</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyAddgrid",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/AddGrid"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/AddSocket",
    "title": "Add Socket",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "grid",
            "description": "<p>grid id</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "geometry",
            "description": "<p>geometry id</p>"
          }
        ]
      }
    },
    "description": "<p>Add a new socket for a single country for a certain grid</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyAddsocket",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/AddSocket"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/AddSource",
    "title": "Add Source",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "grid",
            "description": "<p>grid id</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "geometry",
            "description": "<p>geometry id</p>"
          }
        ]
      }
    },
    "description": "<p>Add a new socket for a single country for a certain grid</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyAddsource",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/AddSource"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/CreateConnection",
    "title": "Create Connection",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "start",
            "description": "<p>ID of the start geometry</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "end",
            "description": "<p>ID of the end geometry</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "cable",
            "description": "<p>ID of the cable geometry</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "coords",
            "description": "<p>coordinates of the starting point, saved as: [123.456, 999.123]</p>"
          }
        ]
      }
    },
    "description": "<p>Create a new connection between 2 points</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyCreateconnection",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/CreateConnection"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/DeleteConnection",
    "title": "Delete Connection",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "cable",
            "description": "<p>ID of the cable geometry</p>"
          }
        ]
      }
    },
    "description": "<p>Deletes a connection</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyDeleteconnection",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/DeleteConnection"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/DeleteGrid",
    "title": "Delete Grid",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>grid id</p>"
          }
        ]
      }
    },
    "description": "<p>Delete a grid and its sockets, sources and energy by the grid id</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyDeletegrid",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/DeleteGrid"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/DeleteOutput",
    "title": "Delete Output",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>geometry id</p>"
          }
        ]
      }
    },
    "description": "<p>Delete the energy_output of a geometry object</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyDeleteoutput",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/DeleteOutput"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/DeleteSocket",
    "title": "Delete Socket",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "geometry",
            "description": "<p>geometry id</p>"
          }
        ]
      }
    },
    "description": "<p>Delete the sockets of a geometry object</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyDeletesocket",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/DeleteSocket"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/DeleteSource",
    "title": "Delete Source",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "geometry",
            "description": "<p>geometry id</p>"
          }
        ]
      }
    },
    "description": "<p>Delete the sources of a geometry object</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyDeletesource",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/DeleteSource"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/GetDependentEnergyPlans",
    "title": "Get Dependent Energy Plans",
    "description": "<p>Get all the plan ids that are dependent on this plan</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan_id",
            "description": "<p>Id of the plan that you want to find the dependent energy plans of.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyGetdependentenergyplans",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/GetDependentEnergyPlans"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/GetOverlappingEnergyPlans",
    "title": "Get Overlapping Energy Plans",
    "description": "<p>Get all the plan ids that are overlapping with this plan. Meaning they are referencing deleted grids in the current plan.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan_id",
            "description": "<p>Id of the plan that you want to find the overlapping energy plans of.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyGetoverlappingenergyplans",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/GetOverlappingEnergyPlans"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/GetPreviousOverlappingPlans",
    "title": "Get Previous Overlapping Energy Plans",
    "description": "<p>Returns whether or not there are overlapping plans in the past that delete grids for the plan that we are querying.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan_id",
            "description": "<p>Id of the plan that you want to find the overlapping energy plans of.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyGetpreviousoverlappingplans",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/GetPreviousOverlappingPlans"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/GetUsedCapacity",
    "title": "Get Used Capacity",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>geometry id</p>"
          }
        ]
      }
    },
    "description": "<p>Get the used capacity of a geometry object in energy_output</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyGetusedcapacity",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/GetUsedCapacity"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/SetDeleted",
    "title": "Set Deleted",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>plan id</p>"
          },
          {
            "group": "Parameter",
            "type": "array",
            "optional": false,
            "field": "delete",
            "description": "<p>Json array of persistent ids of grids to be removed</p>"
          }
        ]
      }
    },
    "description": "<p>Set the grids to be deleted in this plan. Will first remove the previously deleted grids for the plan and then add the new ones. Note that there is no verification if the added values are actually correct.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergySetdeleted",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/SetDeleted"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/SetOutput",
    "title": "Set Output",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>id of geometry</p>"
          },
          {
            "group": "Parameter",
            "type": "float",
            "optional": false,
            "field": "capacity",
            "description": "<p>current node capacity</p>"
          },
          {
            "group": "Parameter",
            "type": "float",
            "optional": false,
            "field": "maxcapacity",
            "description": "<p>maximum capacity of node</p>"
          }
        ]
      }
    },
    "description": "<p>Creates or updates the output of an element</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergySetoutput",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/SetOutput"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/UpdateConnection",
    "title": "Update Connection",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "start",
            "description": "<p>ID of the start geometry</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "end",
            "description": "<p>ID of the end geometry</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "coords",
            "description": "<p>coordinates of the starting point, saved as: [123.456, 999.123]</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "cable",
            "description": "<p>ID of the cable geometry of which to update</p>"
          }
        ]
      }
    },
    "description": "<p>Update cable connection between 2 points</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyUpdateconnection",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/UpdateConnection"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/UpdateGridEnergy",
    "title": "Update Grid Energy",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>grid id</p>"
          },
          {
            "group": "Parameter",
            "type": "array(Object)",
            "optional": false,
            "field": "expected",
            "description": "<p>Objects contain country_id and energy_expected values. E.g. [{&quot;country_id&quot;: 3, &quot;energy_expected&quot;: 1300}]</p>"
          }
        ]
      }
    },
    "description": "<p>Adds new entries to grid_energy and deleted all old grid_energy entries for the given grid</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyUpdategridenergy",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/UpdateGridEnergy"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/UpdateGridName",
    "title": "Update Grid Name",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>grid id</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "name",
            "description": "<p>grid name</p>"
          }
        ]
      }
    },
    "description": "<p>Change the name of a grid</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyUpdategridname",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/UpdateGridName"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/UpdateGridSockets",
    "title": "Update Grid Sockets",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>grid id</p>"
          },
          {
            "group": "Parameter",
            "type": "array(int)",
            "optional": false,
            "field": "sockets",
            "description": "<p>Array of geometry ids for new sockest.</p>"
          }
        ]
      }
    },
    "description": "<p>When called, does the following:\t\t <br/>1. Removes all grid_socket entries with the given grid_socket_grid_id. <br/>2. Adds new entries for all geomID combinations in &quot;grid_socket&quot;, with grid_socket_grid_id set to the given value.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyUpdategridsockets",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/UpdateGridSockets"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/UpdateGridSources",
    "title": "Update Grid Sources",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>grid id</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "sources",
            "description": "<p>a json array of geometry IDs Example: [1,2,3,4]</p>"
          }
        ]
      }
    },
    "description": "<p>When called, does the following:\t\t <br/>1. Removes all grid_source entries with the given grid_source_grid_id. <br/>2. Adds new entries for all country:geomID combinations in &quot;grid_source&quot;, with grid_source_grid_id set to the given value.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyUpdategridsources",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/UpdateGridSources"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/UpdateMaxCapacity",
    "title": "Update Max Capacity",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>geometry id</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "maxcapacity",
            "description": "<p>maximum capacity</p>"
          }
        ]
      }
    },
    "description": "<p>Update the maximum capacity of a geometry object in energy_output</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyUpdatemaxcapacity",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/UpdateMaxCapacity"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/VerifyEnergyCapacity",
    "title": "Verify Energy Capacity",
    "description": "<p>Returns as an array of the supplied geometry ids were <em>not</em> found in the energy_output database table.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "ids",
            "description": "<p>JSON array of integers defining geometry ids to check (e.g. [9554,9562,9563]).</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyVerifyenergycapacity",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/VerifyEnergyCapacity"
      }
    ]
  },
  {
    "group": "Energy",
    "type": "POST",
    "url": "/energy/VerifyEnergyGrid",
    "title": "Verify Energy Grid",
    "description": "<p>Returns a array with client_missing_source_ids, client_extra_source_ids, client_missing_socket_ids, client_extra_socket_ids, each a comma-separated list of ids</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "grid_id",
            "description": "<p>grid id of the grid to verify</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "source_ids",
            "description": "<p>Json array of the grid's source geometry ids on the client</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "socket_ids",
            "description": "<p>Json array of the grid's sockets geometry ids on the client</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.energy.php",
    "groupTitle": "Energy",
    "name": "PostEnergyVerifyenergygrid",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/energy/VerifyEnergyGrid"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/AutoSaveDatabase",
    "title": "AutoSaveDatabase",
    "description": "<p>Creates a session database dump with the naming convention AutoDump_YYY-mm-dd_hh-mm.sql</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGameAutosavedatabase",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/AutoSaveDatabase"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/Config",
    "title": "Config",
    "description": "<p>Obtains the sessions' game configuration</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGameConfig",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/Config"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/GetActualDateForSimulatedMonth",
    "title": "Set Start",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "simulated_month",
            "description": "<p>simulated month ranging from 0..game_end_month</p>"
          }
        ]
      }
    },
    "description": "<p>Returns year and month ([1..12]) of the current requested simulated month identifier. Or -1 on both fields for error.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGameGetactualdateforsimulatedmonth",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/GetActualDateForSimulatedMonth"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/GetCurrentMonth",
    "title": "GetCurrentMonth",
    "description": "<p>Gets the current month of the active game.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGameGetcurrentmonth",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/GetCurrentMonth"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/IsOnline",
    "title": "Is Online",
    "description": "<p>Check if the server is online</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "online",
            "description": ""
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGameIsonline",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/IsOnline"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/latest",
    "title": "Latest game data",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "optional": false,
            "field": "team_id",
            "description": "<p>The team_id (country_id) that we want to get the latest data for.</p>"
          },
          {
            "group": "Parameter",
            "optional": false,
            "field": "last_update_time",
            "description": "<p>The last time the client has received an update tick.</p>"
          },
          {
            "group": "Parameter",
            "optional": false,
            "field": "user",
            "description": "<p>The id of the user logged on to the client requesting the update.</p>"
          }
        ]
      }
    },
    "description": "<p>Gets the latest plans &amp; messages from the server</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGameLatest",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/latest"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/meta",
    "title": "Meta",
    "description": "<p>Get all layer meta data required for a game</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "JSON",
            "description": "<p>object</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGameMeta",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/meta"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/NextMonth",
    "title": "NextMonth",
    "description": "<p>Updates session database to indicate start of next simulated month</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGameNextmonth",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/NextMonth"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/planning",
    "title": "Planning",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "months",
            "description": "<p>the amount of months the planning phase takes</p>"
          }
        ]
      }
    },
    "description": "<p>set the amount of months the planning phase takes, should not be done during the simulation phase</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGamePlanning",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/planning"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/realtime",
    "title": "Realtime",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "realtime",
            "description": "<p>length of planning phase (in seconds)</p>"
          }
        ]
      }
    },
    "description": "<p>Set the duration of the planning phase in seconds</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGameRealtime",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/realtime"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/realtime",
    "title": "FutureRealtime",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "realtime",
            "description": "<p>comma separated string of all the era times</p>"
          }
        ]
      }
    },
    "description": "<p>Set the duration of future eras</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGameRealtime",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/realtime"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/state",
    "title": "State",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "state",
            "description": "<p>new state of the game</p>"
          }
        ]
      }
    },
    "description": "<p>Set the current game state</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGameState",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/state"
      }
    ]
  },
  {
    "group": "Game",
    "type": "POST",
    "url": "/game/tick",
    "title": "Tick",
    "description": "<p>Tick the game server, updating the plans if required</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "JSON",
            "description": "<p>object</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.game.php",
    "groupTitle": "Game",
    "name": "PostGameTick",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/game/tick"
      }
    ]
  },
  {
    "group": "GameSession",
    "description": "<p>Archives a game session with a specified ID.</p>",
    "type": "POST",
    "url": "/GameSession/ArchiveGameSession",
    "title": "Archives game session",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "response_url",
            "description": "<p>API call that we make with the zip encoded in the body upon completion.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.gamesession.php",
    "groupTitle": "GameSession",
    "name": "PostGamesessionArchivegamesession",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/GameSession/ArchiveGameSession"
      }
    ]
  },
  {
    "group": "GameSession",
    "description": "<p>Archives a game session with a specified ID.</p>",
    "type": "POST",
    "url": "/GameSession/ArchiveGameSessionInternal",
    "title": "Archives game session, internal method",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "response_url",
            "description": "<p>API call that we make with the zip path upon completion.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.gamesession.php",
    "groupTitle": "GameSession",
    "name": "PostGamesessionArchivegamesessioninternal",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/GameSession/ArchiveGameSessionInternal"
      }
    ]
  },
  {
    "group": "GameSession",
    "description": "<p>Sets up a new game session with the supplied information.</p>",
    "type": "POST",
    "url": "/GameSession/CreateGameSession",
    "title": "Creates new game session",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "game_id",
            "description": "<p>Session identifier for this game.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "config_file_content",
            "description": "<p>JSON Object of the config file.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "password_admin",
            "description": "<p>Plain-text admin password.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "password_player",
            "description": "<p>Plain-text player password.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "watchdog_address",
            "description": "<p>URL at which the watchdog resides for this session.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "response_address",
            "description": "<p>URL which we call when the setup is done.</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "allow_recreate",
            "description": "<p>(0|1) Allow overwriting of an existing session?</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.gamesession.php",
    "groupTitle": "GameSession",
    "name": "PostGamesessionCreategamesession",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/GameSession/CreateGameSession"
      }
    ]
  },
  {
    "group": "GameSession",
    "description": "<p>For internal use: creates a new game session with the given config file path.</p>",
    "type": "POST",
    "url": "/GameSession/CreateGameSession",
    "title": "Creates new game session",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "config_file_path",
            "description": "<p>Local path to the config file.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "password_admin",
            "description": "<p>Admin password for this session</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "password_player",
            "description": "<p>Player password for this session</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "watchdog_address",
            "description": "<p>API Address to direct all Watchdog calls to.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "response_address",
            "description": "<p>URL which we call when the setup is done.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.gamesession.php",
    "groupTitle": "GameSession",
    "name": "PostGamesessionCreategamesession",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/GameSession/CreateGameSession"
      }
    ]
  },
  {
    "group": "Geometry",
    "type": "POST",
    "url": "/geometry/Data",
    "title": "Data",
    "description": "<p>Adjust geometry metadata and type</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>geometry id to update</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>metadata of the geometry to set</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "type",
            "description": "<p>type value, either single integer or comma-separated multiple integers</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.geometry.php",
    "groupTitle": "Geometry",
    "name": "PostGeometryData",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/geometry/Data"
      }
    ]
  },
  {
    "group": "Geometry",
    "type": "POST",
    "url": "/geometry/Delete",
    "title": "Delete",
    "description": "<p>Delete geometry without using a plan</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>geometry id to delete, marks a row as inactive</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.geometry.php",
    "groupTitle": "Geometry",
    "name": "PostGeometryDelete",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/geometry/Delete"
      }
    ]
  },
  {
    "group": "Geometry",
    "type": "POST",
    "url": "/geometry/MarkForDelete",
    "title": "MarkForDelete",
    "description": "<p>Delete geometry using a plan, this will be triggered at the execution time of a plan</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>geometry persistent id to delete</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>plan id where the geometry will be deleted</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "layer",
            "description": "<p>the layer id where the geometry will be deleted</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.geometry.php",
    "groupTitle": "Geometry",
    "name": "PostGeometryMarkfordelete",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/geometry/MarkForDelete"
      }
    ]
  },
  {
    "group": "Geometry",
    "type": "post",
    "url": "/geometry/post",
    "title": "Post",
    "description": "<p>Create a new geometry entry in a plan</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "layer",
            "description": "<p>id of layer to post in</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "geometry",
            "description": "<p>string of geometry to post</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>id of the plan</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "FID",
            "description": "<p>(optional) FID of geometry</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "persistent",
            "description": "<p>(optional) persistent ID of geometry</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>(optional) meta data string of geometry object</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "country",
            "description": "<p>(optional) The owning country id. NULL or -1 if no country is set.</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>of the newly created geometry</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.geometry.php",
    "groupTitle": "Geometry",
    "name": "PostGeometryPost",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/geometry/post"
      }
    ]
  },
  {
    "group": "Geometry",
    "type": "POST",
    "url": "/geometry/PostSubtractive",
    "title": "Post Subtractive",
    "description": "<p>Create a new subtractive polygon on an existing polygon</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "layer",
            "description": "<p>id of layer to post in</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "geometry",
            "description": "<p>string of geometry to post</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "subtractive",
            "description": "<p>id of the polygon the newly created polygon is subtractive to</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "FID",
            "description": "<p>(optional) FID of geometry</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "persistent",
            "description": "<p>(optional) persistent ID of geometry</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.geometry.php",
    "groupTitle": "Geometry",
    "name": "PostGeometryPostsubtractive",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/geometry/PostSubtractive"
      }
    ]
  },
  {
    "group": "Geometry",
    "type": "POST",
    "url": "/geometry/UnmarkForDelete",
    "title": "UnmarkForDelete",
    "description": "<p>Remove the deletion of a geometry put in the plan</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>geometry persistent id to undelete</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>plan id where the geometry is located in</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.geometry.php",
    "groupTitle": "Geometry",
    "name": "PostGeometryUnmarkfordelete",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/geometry/UnmarkForDelete"
      }
    ]
  },
  {
    "group": "Geometry",
    "type": "POST",
    "url": "/geometry/update",
    "title": "Update",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>geometry id to update</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "geometry",
            "description": "<p>string of geometry json to post</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "country",
            "description": "<p>country id to set as geometry's owner</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>same geometry id</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.geometry.php",
    "groupTitle": "Geometry",
    "name": "PostGeometryUpdate",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/geometry/update"
      }
    ]
  },
  {
    "group": "KPI",
    "type": "POST",
    "url": "/kpi/BatchPost",
    "title": "BatchPost",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "array",
            "optional": false,
            "field": "kpiValues",
            "description": "<p>Input format should be [{&quot;name&quot;:(string kpiName),&quot;month&quot;: (int month), &quot;value&quot;:(float kpiValue),&quot;type&quot;:(string kpiType),&quot;unit&quot;:(string kpiUnit),&quot;country&quot;:(int countryId or null)}]</p>"
          }
        ]
      }
    },
    "description": "<p>Add a new kpi value to the database</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.kpi.php",
    "groupTitle": "KPI",
    "name": "PostKpiBatchpost",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/kpi/BatchPost"
      }
    ]
  },
  {
    "group": "KPI",
    "type": "POST",
    "url": "/kpi/post",
    "title": "Post",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "name",
            "description": "<p>name of the KPI</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "month",
            "description": "<p>Month that this KPI applies to.</p>"
          },
          {
            "group": "Parameter",
            "type": "float",
            "optional": false,
            "field": "value",
            "description": "<p>the value of this months kpi</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "type",
            "description": "<p>the type of KPI (ECOLOGY, ENERGY, SHIPPING)</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "unit",
            "description": "<p>the measurement unit of this KPI</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "country",
            "description": "<p>(OPTIONAL) id of the country that this belongs to. Not filling this in will default it to all countries</p>"
          }
        ]
      }
    },
    "description": "<p>Add a new kpi value to the database</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.kpi.php",
    "groupTitle": "KPI",
    "name": "PostKpiPost",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/kpi/post"
      }
    ]
  },
  {
    "group": "Layer",
    "description": "<p>Set a layer as inactive, without actually deleting it completely from the session database</p>",
    "type": "POST",
    "url": "/layer/Delete/",
    "title": "",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "layer_id",
            "description": "<p>Target layer id</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.layer.php",
    "groupTitle": "Layer",
    "name": "PostLayerDelete",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/layer/Delete/"
      }
    ]
  },
  {
    "group": "Layer",
    "description": "<p>Export a layer to .json</p>",
    "type": "POST",
    "url": "/layer/Export/",
    "title": "Export",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "layer_id",
            "description": "<p>id of the layer to export</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "json",
            "description": "<p>formatted layer export with all geometry and their attributes</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.layer.php",
    "groupTitle": "Layer",
    "name": "PostLayerExport",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/layer/Export/"
      }
    ]
  },
  {
    "group": "Layer",
    "description": "<p>Get all geometry in a single layer</p>",
    "type": "POST",
    "url": "/layer/get/",
    "title": "Get",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "layer_id",
            "description": "<p>id of the layer to return</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "JSON",
            "description": "<p>JSON object</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.layer.php",
    "groupTitle": "Layer",
    "name": "PostLayerGet",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/layer/get/"
      }
    ]
  },
  {
    "group": "Layer",
    "type": "POST",
    "url": "/layer/GetRaster",
    "title": "GetRaster",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "optional": false,
            "field": "layer_name",
            "description": "<p>Name of the layer corresponding to the image data.</p>"
          }
        ]
      }
    },
    "description": "<p>Retrieves image data for raster.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "optional": false,
            "field": "Returns",
            "description": "<p>array of displayed_bounds and image_data strings to payload, whereby image_data is base64 encoded file</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.layer.php",
    "groupTitle": "Layer",
    "name": "PostLayerGetraster",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/layer/GetRaster"
      }
    ]
  },
  {
    "group": "Layer",
    "description": "<p>Import metadata for a set of layers as defined under 'meta' in the session's config file</p>",
    "type": "POST",
    "url": "/layer/ImportMeta",
    "title": "",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "configFilename",
            "description": ""
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "geoserver_url",
            "description": ""
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "geoserver_username",
            "description": ""
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "geoserver_password",
            "description": ""
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.layer.php",
    "groupTitle": "Layer",
    "name": "PostLayerImportmeta",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/layer/ImportMeta"
      }
    ]
  },
  {
    "group": "Layer",
    "type": "POST",
    "url": "/layer/List",
    "title": "",
    "description": "<p>List Provides a list of raster layers and vector layers that have active geometry.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "optional": false,
            "field": "Returns",
            "description": "<p>an array of layers, with layer_id, layer_name and layer_geotype objects defined per layer.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.layer.php",
    "groupTitle": "Layer",
    "name": "PostLayerList",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/layer/List"
      }
    ]
  },
  {
    "group": "Layer",
    "description": "<p>Get all the meta data of a single layer</p>",
    "type": "POST",
    "url": "/layer/meta/",
    "title": "Meta",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "layer_id",
            "description": "<p>layer id to return</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "JSON",
            "description": "<p>JSON Object</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.layer.php",
    "groupTitle": "Layer",
    "name": "PostLayerMeta",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/layer/meta/"
      }
    ]
  },
  {
    "group": "Layer",
    "description": "<p>Gets a single layer meta data by name.</p>",
    "type": "POST",
    "url": "/layer/MetaByName",
    "title": "MetaByName",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "name",
            "description": "<p>name of the layer that we want the meta for</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "JSON",
            "description": "<p>JSON Object.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.layer.php",
    "groupTitle": "Layer",
    "name": "PostLayerMetabyname",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/layer/MetaByName"
      }
    ]
  },
  {
    "group": "Layer",
    "description": "<p>Create a new empty layer</p>",
    "type": "POST",
    "url": "/layer/post/:id",
    "title": "Post",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "name",
            "description": "<p>name of the layer</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "geotype",
            "description": "<p>geotype of the layer</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>id of the new layer</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.layer.php",
    "groupTitle": "Layer",
    "name": "PostLayerPostId",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/layer/post/:id"
      }
    ]
  },
  {
    "group": "Layer",
    "description": "<p>Update the meta data of a layer</p>",
    "type": "POST",
    "url": "/layer/UpdateMeta",
    "title": "UpdateMeta",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "short",
            "description": "<p>Update the display name of a layer</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "category",
            "description": "<p>Update the category of a layer</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "subcategory",
            "description": "<p>Update the subcategory of a layer</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "type",
            "description": "<p>Update the type field of a layer</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "depth",
            "description": "<p>Update the depth of a layer</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>id of the layer to update</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>id of the new layer</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.layer.php",
    "groupTitle": "Layer",
    "name": "PostLayerUpdatemeta",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/layer/UpdateMeta"
      }
    ]
  },
  {
    "group": "Layer",
    "type": "POST",
    "url": "/layer/UpdateRaster",
    "title": "",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "layer_name",
            "description": "<p>Name of the layer the raster image is for.</p>"
          },
          {
            "group": "Parameter",
            "type": "array",
            "optional": false,
            "field": "raster_bounds",
            "description": "<p>2x2 array of doubles specifying [[min X, min Y], [max X, max Y]]</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "image_data",
            "description": "<p>Base64 encoded string of image data.</p>"
          }
        ]
      }
    },
    "description": "<p>UpdateRaster updates raster image</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.layer.php",
    "groupTitle": "Layer",
    "name": "PostLayerUpdateraster",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/layer/UpdateRaster"
      }
    ]
  },
  {
    "group": "Log",
    "description": "<p>Posts an 'error' event in the server log.</p>",
    "type": "POST",
    "url": "/Log/Event",
    "title": "Event",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "source",
            "description": "<p>Source component of the error. Examples: Server, MEL, CEL, SEL etc.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "severity",
            "description": "<p>Severity of the errror [&quot;Warning&quot;|&quot;Error&quot;|&quot;Fatal&quot;]</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "message",
            "description": "<p>Debugging information associated with this event</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "stack_trace",
            "description": "<p>Debug stacktrace where the error occured. Optional.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.log.php",
    "groupTitle": "Log",
    "name": "PostLogEvent",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/Log/Event"
      }
    ]
  },
  {
    "group": "MEL",
    "description": "<p>Gets all the geometry data of a layer</p>",
    "type": "POST",
    "url": "/mel/GeometryExportName",
    "title": "Geometry Export Name",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "layer",
            "description": "<p>name to return the geometry data for</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "layer_type",
            "description": "<p>type within the layer to return. -1 for all types.</p>"
          },
          {
            "group": "Parameter",
            "type": "bool",
            "optional": false,
            "field": "construction_only",
            "description": "<p>whether or not to return data only if it's being constructed.</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "JSON",
            "description": "<p>JSON Object</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.mel.php",
    "groupTitle": "MEL",
    "name": "PostMelGeometryexportname",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/mel/GeometryExportName"
      }
    ]
  },
  {
    "group": "Objective",
    "type": "POST",
    "url": "/objective/delete",
    "title": "Delete",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>id of the objective to delete</p>"
          }
        ]
      }
    },
    "description": "<p>Set an objective to be inactive</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.objective.php",
    "groupTitle": "Objective",
    "name": "PostObjectiveDelete",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/objective/delete"
      }
    ]
  },
  {
    "group": "Objective",
    "type": "POST",
    "url": "/objective/post",
    "title": "Post",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "country",
            "description": "<p>country id, set -1 if you want to add an objective to all countries</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "title",
            "description": "<p>objective title</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "description",
            "description": "<p>objective description</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "deadline",
            "description": "<p>game month when this task needs to be completed by</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "tasks",
            "description": "<p>JSON array with task objects. Example: [{&quot;sectorname&quot;:&quot;&quot;,&quot;category&quot;:&quot;&quot;,&quot;subcategory&quot;:&quot;&quot;,&quot;function&quot;:&quot;&quot;,&quot;value&quot;:0,&quot;description&quot;:&quot;&quot;}]</p>"
          }
        ]
      }
    },
    "description": "<p>Add a new objective with tasks to a country (or all countries at once)</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.objective.php",
    "groupTitle": "Objective",
    "name": "PostObjectivePost",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/objective/post"
      }
    ]
  },
  {
    "group": "Objective",
    "type": "POST",
    "url": "/objective/SetCompleted",
    "title": "SetCompleted",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "objective_id",
            "description": "<p>id of the objective to set the completed state for</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "completed",
            "description": "<p>State (0 or 1) of the completed flag to set</p>"
          }
        ]
      }
    },
    "description": "<p>Changes the completed state of an objective.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.objective.php",
    "groupTitle": "Objective",
    "name": "PostObjectiveSetcompleted",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/objective/SetCompleted"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Add a new list of countries that require approval for a plan</p>",
    "type": "POST",
    "url": "/plan/AddApproval",
    "title": "Add Approval",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>id of the plan</p>"
          },
          {
            "group": "Parameter",
            "type": "array",
            "optional": false,
            "field": "countries",
            "description": "<p>json array of country ids</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanAddapproval",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/AddApproval"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Get all plans</p>",
    "type": "POST",
    "url": "/plan/all",
    "title": "All",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "JSON",
            "description": "<p>object of all plan metadata + comments</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanAll",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/all"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Change plan date</p>",
    "type": "POST",
    "url": "/plan/date",
    "title": "Date",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>plan id</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "date",
            "description": "<p>new plan date</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanDate",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/date"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Delete a plan</p>",
    "type": "POST",
    "url": "/plan/delete",
    "title": "Delete",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>of the plan to delete</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanDelete",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/delete"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Delete all required approvals for a plan, either when it's not necessary anymore or when you need to submit a new list</p>",
    "type": "POST",
    "url": "/plan/DeleteApproval",
    "title": "Delete Approval",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>id of the plan</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanDeleteapproval",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/DeleteApproval"
      }
    ]
  },
  {
    "group": "Plan",
    "type": "POST",
    "url": "/plan/DeleteEnergy",
    "title": "Delete Energy",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>plan id</p>"
          }
        ]
      }
    },
    "description": "<p>delete all grids &amp; associated grid data based on a plan id</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanDeleteenergy",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/DeleteEnergy"
      }
    ]
  },
  {
    "group": "Plan",
    "type": "POST",
    "url": "/plan/DeleteFishing",
    "title": "Delete Fishing",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>plan id</p>"
          }
        ]
      }
    },
    "description": "<p>delete all the fishing settings associated with a plan</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanDeletefishing",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/DeleteFishing"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Delete a layer from a plan</p>",
    "type": "POST",
    "url": "/plan/DeleteLayer",
    "title": "Delete Layer",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>id of the layer to remove</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanDeletelayer",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/DeleteLayer"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Returns a json-encoded object which represents the exported plan data for the current game session. Returns an empty string on failure.</p>",
    "type": "POST",
    "url": "/plan/DeleteLayer",
    "title": "Delete Layer",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "JSON",
            "description": "<p>encoded object with fields &quot;success&quot; (0|1) Successful operation?, &quot;message&quot; (string) Error messages that might have occured, &quot;data&quot; (object) Exported object that represents the exported plan data.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanDeletelayer",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/DeleteLayer"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Update the description</p>",
    "type": "POST",
    "url": "/plan/description",
    "title": "Description",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>plan id</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "description",
            "description": "<p>new plan description</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanDescription",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/description"
      }
    ]
  },
  {
    "group": "Plan",
    "type": "POST",
    "url": "/plan/fishing",
    "title": "Fishing",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>plan id</p>"
          },
          {
            "group": "Parameter",
            "type": "array",
            "optional": false,
            "field": "fishing_values",
            "description": "<p>JSON encoded key value pair array of fishing values</p>"
          }
        ]
      }
    },
    "description": "<p>Sets the fishing values for a plan to the fishing_values included in the call. Will delete all fishing values that existed before this plan.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanFishing",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/fishing"
      }
    ]
  },
  {
    "group": "Plan",
    "type": "POST",
    "url": "/plan/GetInitialFishingValues",
    "title": "GetInitialFishingValues",
    "description": "<p>Returns the initial fishing values submitted by MEL. The values are in a 0..1 range for each fishing fleet and country. Fishing fleet values summed together should be in the range of 0..1</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanGetinitialfishingvalues",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/GetInitialFishingValues"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Add a new layer to a plan</p>",
    "type": "POST",
    "url": "/plan/layer",
    "title": "Layer",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>id of the plan</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "layerid",
            "description": "<p>id of the original layer</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanLayer",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/layer"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Lock a plan</p>",
    "type": "POST",
    "url": "/plan/lock",
    "title": "Lock",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>plan id</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "int",
            "optional": false,
            "field": "success",
            "description": "<p>1</p>"
          },
          {
            "group": "Success 200",
            "type": "int",
            "optional": false,
            "field": "failure",
            "description": "<p>-1</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanLock",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/lock"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Add a message to a plan</p>",
    "type": "POST",
    "url": "/plan/message",
    "title": "Message",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>Plan id that this message applies to.</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "team_id",
            "description": "<p>Team (Country) ID that this message originated from.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "user_name",
            "description": "<p>Display name of the user that sent this message.</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "text",
            "description": "<p>Message sent by the user</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanMessage",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/message"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Rename a plan</p>",
    "type": "POST",
    "url": "/plan/name",
    "title": "Name",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>plan id</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "name",
            "description": "<p>new plan name</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanName",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/name"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Create a new plan</p>",
    "type": "POST",
    "url": "/plan/post",
    "title": "Post",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "country",
            "description": "<p>country id</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "name",
            "description": "<p>name of the plan</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "time",
            "description": "<p>when the plan has to be implemented (months since start of project)</p>"
          },
          {
            "group": "Parameter",
            "type": "array",
            "optional": false,
            "field": "layers",
            "description": "<p>json array of layer ids (e.g. [1,4,82])</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "type",
            "description": "<p>Comma separated string representing the plan type in the format of &quot;[isEnergy], [isEcology], [isShipping]&quot;, e.g. &quot;0, 1, 1&quot;.</p>"
          },
          {
            "group": "Parameter",
            "type": "boolean",
            "optional": false,
            "field": "alters_energy_distribution",
            "description": "<p>, in format 0/1, following energy distribution checkbox in Plan Wizard Step 2b</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>id</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanPost",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/post"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Get all layer restrictions</p>",
    "type": "POST",
    "url": "/plan/restrictions",
    "title": "Restrictions",
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanRestrictions",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/restrictions"
      }
    ]
  },
  {
    "group": "Plan",
    "type": "POST",
    "url": "/plan/SetEnergyDistribution",
    "title": "Set Energy Distribution",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>plan id</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "alters_energy_distribution",
            "description": "<p>boolean [0|1]</p>"
          }
        ]
      }
    },
    "description": "<p>set the energy distribution flag of a single plan</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanSetenergydistribution",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/SetEnergyDistribution"
      }
    ]
  },
  {
    "group": "Plan",
    "type": "POST",
    "url": "/plan/SetEnergyError",
    "title": "Set Energy Error",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>plan id</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "error",
            "description": "<p>error boolean [0|1]</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "check_dependent_plans",
            "description": "<p>boolean [0|1] Check dependent plans and set them to error as well? Only works when setting plans to error 1</p>"
          }
        ]
      }
    },
    "description": "<p>set the energy error flag of a single plan</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanSetenergyerror",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/SetEnergyError"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Updates or sets the restrction area sizes for this plan.</p>",
    "type": "POST",
    "url": "/plan/SetRestrictionAreas",
    "title": "Set Restriction Areas",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan_id",
            "description": "<p>Plan Id</p>"
          },
          {
            "group": "Parameter",
            "type": "array",
            "optional": false,
            "field": "settings",
            "description": "<p>Json array restriction area settings</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanSetrestrictionareas",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/SetRestrictionAreas"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Set the state of a plan</p>",
    "type": "POST",
    "url": "/plan/SetState",
    "title": "Set State",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>id of the plan</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "state",
            "description": "<p>state to set the plan to (DESIGN, CONSULTATION, APPROVAL, APPROVED, DELETED)</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanSetstate",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/SetState"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Update the plan type</p>",
    "type": "POST",
    "url": "/plan/type",
    "title": "Type",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>plan id</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "type",
            "description": "<p>comma separated string of the plan types, values can be &quot;ecology&quot;, &quot;shipping&quot; or &quot;energy&quot; (e.g. &quot;ecology,energy&quot;). Empty if none apply</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanType",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/type"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Unlock a plan</p>",
    "type": "POST",
    "url": "/plan/unlock",
    "title": "Unlock",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>plan id</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "force_unlock",
            "description": "<p>(0|1) Force unlock a plan. Don't check for the correct user, just do it.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanUnlock",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/unlock"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Add a new list of countries that require approval for a plan</p>",
    "type": "POST",
    "url": "/plan/Vote",
    "title": "Vote",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "country",
            "description": "<p>country id</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "plan",
            "description": "<p>plan id</p>"
          },
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "vote",
            "description": "<p>(-1 = undecided/abstain, 0 = no, 1 = yes)</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPlanVote",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/plan/Vote"
      }
    ]
  },
  {
    "group": "Plan",
    "description": "<p>Get a specific plan</p>",
    "type": "POST",
    "url": "/post/get",
    "title": "Get",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "int",
            "optional": false,
            "field": "id",
            "description": "<p>of plan to return</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "JSON",
            "description": "<p>object containing all plan metadata + comments</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.plan.php",
    "groupTitle": "Plan",
    "name": "PostPostGet",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/post/get"
      }
    ]
  },
  {
    "group": "REL",
    "type": "POST",
    "url": "/rel/GetConfiguration",
    "title": "GetConfiguration",
    "description": "<p>Returns object containing configuration values for REL.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.rel.php",
    "groupTitle": "REL",
    "name": "PostRelGetconfiguration",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/rel/GetConfiguration"
      }
    ]
  },
  {
    "group": "REL",
    "type": "POST",
    "url": "/rel/GetRestrictionGeometry",
    "title": "GetRestrictionGeometry",
    "description": "<p>Returns all restriction geometry that appears on a configured restriction layer. Geometry_type is translated into geometry type that corresponds to the type configured and communicated to Marin API.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.rel.php",
    "groupTitle": "REL",
    "name": "PostRelGetrestrictiongeometry",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/rel/GetRestrictionGeometry"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetConfiguredRouteIntensities",
    "title": "GetConfiguredRouteIntensities",
    "description": "<p>Returns the configured routes setup in the config file as an JSON encoded array.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetconfiguredrouteintensities",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetConfiguredRouteIntensities"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetCountryBorderGeometry",
    "title": "GetCountryBorderGeometry",
    "description": "<p>Returns all geometry which defines the areas of each country. For instance in the NorthSea this will be the EEZ layer</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetcountrybordergeometry",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetCountryBorderGeometry"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetHeatmapOutputSettings",
    "title": "GetHeatmapOutputSettings",
    "description": "<p>Gets heatmap settings as defined in the config file. These settings include the output size, internal layer name, raster output location, raster bounds and intensity mappings.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetheatmapoutputsettings",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetHeatmapOutputSettings"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetHeatmapSettings",
    "title": "GetHeatmapSettings",
    "description": "<p>Gets the persistent riskmap settings.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetheatmapsettings",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetHeatmapSettings"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetPlayableAreaGeometry",
    "title": "GetPlayableAreaGeometry",
    "description": "<p>Gets the geometry associated with the playable area layer</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetplayableareageometry",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetPlayableAreaGeometry"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetPortIntensities",
    "title": "GetPortIntensities",
    "description": "<p>Returns all the configured intensities for the shipping ports indexed by port geometry id.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetportintensities",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetPortIntensities"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetRestrictionGeometry",
    "title": "GetRestrictionGeometry",
    "description": "<p>Gets all of the restriction geometry that the ships aren't allowed to cross</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetrestrictiongeometry",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetRestrictionGeometry"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetSELConfig",
    "title": "GetSELConfig",
    "description": "<p>Returns a collection of region-specific SEL config values.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetselconfig",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetSELConfig"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetSELGameClientConfig",
    "title": "Get Game Client Config",
    "description": "<p>Returns a json object of game-client specific settings related to shipping.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetselgameclientconfig",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetSELGameClientConfig"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetShippingLaneGeometry",
    "title": "GetShippingLaneGeometry",
    "description": "<p>Returns all the geometry associated with shipping lanes.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetshippinglanegeometry",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetShippingLaneGeometry"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetShippingPortGeometry",
    "title": "GetShippingPortGeometry",
    "description": "<p>Returns all geometry associated with shipping ports for the current game.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetshippingportgeometry",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetShippingPortGeometry"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetShipRestrictionGroupExceptions",
    "title": "GetShipRestrictionGroupExceptions",
    "description": "<p>Returns all restriction group exceptions configured in the configuration file. Returns the data in the format of { &quot;layer_id&quot;: [int layerId], &quot;layer_type&quot;: [string layerType], &quot;allowed_restriction_groups&quot;: [int[] shipRestrictionGroups] }</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetshiprestrictiongroupexceptions",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetShipRestrictionGroupExceptions"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetShipTypes",
    "title": "GetShipTypes",
    "description": "<p>Returns all configured ship types for the current session</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetshiptypes",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetShipTypes"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/GetUpdatePackage",
    "title": "Get update package",
    "description": "<p>Get an update package which describes whan needs to be updated in the SEL program.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelGetupdatepackage",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/GetUpdatePackage"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/NotifyUpdateFinished",
    "title": "Notify Update Finished",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "optional": false,
            "field": "month",
            "description": "<p>The month this update was completed for.</p>"
          }
        ]
      }
    },
    "description": "<p>Notifies the server that SEL has finished the update for this month.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelNotifyupdatefinished",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/NotifyUpdateFinished"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/ReimportShippingLayers",
    "title": "ReimportShippingLayers",
    "description": "<p>Creates the raster layers required for shipping.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelReimportshippinglayers",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/ReimportShippingLayers"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/SetRastersUpdated/:layer_names",
    "title": "SetRastersUpdated",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string[]",
            "optional": false,
            "field": "layer_names",
            "description": "<p>json-encoded array of layer names that have been updated (e.g. [&quot;Layer1&quot;, &quot;Layer2&quot;, &quot;Layer3&quot;]</p>"
          }
        ]
      }
    },
    "description": "<p>Notifies the running game that all the configured rasters have been updated to a more recent version so the game will reload them.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelSetrastersupdatedLayer_names",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/SetRastersUpdated/:layer_names"
      }
    ]
  },
  {
    "group": "SEL",
    "type": "POST",
    "url": "/sel/SetShippingIntensityValues",
    "title": "SetShippingIntensityValues",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "values",
            "description": "<p>Json encoded string of an &lt;int, int&gt; key value pair where keys define the geometry ID and the values define the shipping intensity.</p>"
          }
        ]
      }
    },
    "description": "<p>Sets the &quot;Shipping_Intensity&quot; data field to the supplied value for all submitted IDs</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.sel.php",
    "groupTitle": "SEL",
    "name": "PostSelSetshippingintensityvalues",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/sel/SetShippingIntensityValues"
      }
    ]
  },
  {
    "group": "Security",
    "type": "POST",
    "url": "/security/CheckAccess",
    "title": "CheckAccess",
    "description": "<p>Checks if the the current access token is valid to access a certain level. Currently only checks for full access tokens.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "optional": false,
            "field": "Returns",
            "description": "<p>json object indicating status of the current token. { &quot;status&quot;: [&quot;Valid&quot;|&quot;UpForRenewal&quot;|&quot;Expired&quot;] }</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.security.php",
    "groupTitle": "Security",
    "name": "PostSecurityCheckaccess",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/security/CheckAccess"
      }
    ]
  },
  {
    "group": "Security",
    "type": "POST",
    "url": "/security/RequestToken",
    "title": "RequestToken",
    "description": "<p>Requests a new access token for the API.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "optional": false,
            "field": "expired_token",
            "description": "<p>OPTIONAL A previously used access token that is now expired. Needs a valid REQUEST_ACCESS token to be sent with the request before it generates a new token with the same access as the expired token.</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "optional": false,
            "field": "Returns",
            "description": "<p>json object indicating success and the token containing token identifier and unix timestap for until when it's valid. { &quot;success&quot;: [0|1], &quot;token&quot;: { &quot;token&quot;: [identifier], &quot;valid_until&quot;: [timestamp]&quot; }</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.security.php",
    "groupTitle": "Security",
    "name": "PostSecurityRequesttoken",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/security/RequestToken"
      }
    ]
  },
  {
    "group": "Simulations",
    "type": "POST",
    "url": "/Simulations/GetConfiguredSimulationTypes",
    "title": "Get Configured Simulation Types",
    "description": "<p>Get Configured Simulation Types (e.g. [&quot;MEL&quot;, &quot;SEL&quot;, &quot;CEL&quot;])</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "array",
            "optional": false,
            "field": "Returns",
            "description": "<p>the type name of the simulations present in the current configuration.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.simulations.php",
    "groupTitle": "Simulations",
    "name": "PostSimulationsGetconfiguredsimulationtypes",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/Simulations/GetConfiguredSimulationTypes"
      }
    ]
  },
  {
    "group": "Simulations",
    "type": "POST",
    "url": "/Simulations/GetSimulationRequestedState",
    "title": "Get Simulation Requested State",
    "description": "<p>Get requested running state of the simulation.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "Currently",
            "description": "<p>requested state for simulations. [Started, Stopped]</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.simulations.php",
    "groupTitle": "Simulations",
    "name": "PostSimulationsGetsimulationrequestedstate",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/Simulations/GetSimulationRequestedState"
      }
    ]
  },
  {
    "group": "Simulations",
    "type": "POST",
    "url": "/Simulations/GetWatchdogTokenForServer",
    "title": "Get Watchdog Token ForServer",
    "description": "<p>Get the watchdog token for the current server. Used for setting up debug bridge in simulations.</p>",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "array",
            "optional": false,
            "field": "with",
            "description": "<p>watchdog_token key and value</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.simulations.php",
    "groupTitle": "Simulations",
    "name": "PostSimulationsGetwatchdogtokenforserver",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/Simulations/GetWatchdogTokenForServer"
      }
    ]
  },
  {
    "group": "Simulations",
    "type": "POST",
    "url": "/Simulations/WatchdogStartSimulations",
    "title": "Watchdog Start Simulations",
    "description": "<p>Set the state so the watchdog will keep simulations running.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.simulations.php",
    "groupTitle": "Simulations",
    "name": "PostSimulationsWatchdogstartsimulations",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/Simulations/WatchdogStartSimulations"
      }
    ]
  },
  {
    "group": "Simulations",
    "type": "POST",
    "url": "/Simulations/WatchdogStopSimulations",
    "title": "Watchdog Stop Simulations",
    "description": "<p>Stop all simulations maintained by watchdog.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.simulations.php",
    "groupTitle": "Simulations",
    "name": "PostSimulationsWatchdogstopsimulations",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/Simulations/WatchdogStopSimulations"
      }
    ]
  },
  {
    "group": "Update",
    "type": "POST",
    "url": "/update/Reimport",
    "title": "Reimport",
    "description": "<p>Performs a full reimport of the database with the set filename in $configFilename.</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.update.php",
    "groupTitle": "Update",
    "name": "PostUpdateReimport",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/update/Reimport"
      }
    ]
  },
  {
    "group": "User",
    "description": "<p>Creates a new session for the desired country id.</p>",
    "type": "POST",
    "url": "/user/RequestSession",
    "title": "Set State",
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "json",
            "optional": false,
            "field": "Returns",
            "description": "<p>a json object describing the 'success' state, the 'session_id' generated for the user. And in case of a failure a 'message' that describes what went wrong.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "api/v1/class.user.php",
    "groupTitle": "User",
    "name": "PostUserRequestsession",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/user/RequestSession"
      }
    ]
  },
  {
    "group": "Warning",
    "type": "POST",
    "url": "/warning/post",
    "title": "Post",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "added",
            "optional": false,
            "field": "Json",
            "description": "<p>array of IssueObjects that are added.</p>"
          }
        ]
      }
    },
    "description": "<p>Add or update a warning message on the server</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.warning.php",
    "groupTitle": "Warning",
    "name": "PostWarningPost",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/warning/post"
      }
    ]
  },
  {
    "group": "Warning",
    "type": "POST",
    "url": "/warning/SetShippingIssues",
    "title": "Set shipping issues",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "optional": false,
            "field": "issues",
            "description": "<p>The JSON encoded issues of SEL.APIShippingIssue type.</p>"
          }
        ]
      }
    },
    "description": "<p>Clears out the old shipping issues and creates new shipping issues defined by issues</p>",
    "version": "0.0.0",
    "filename": "api/v1/class.warning.php",
    "groupTitle": "Warning",
    "name": "PostWarningSetshippingissues",
    "sampleRequest": [
      {
        "url": "http://localhost/api/1/warning/SetShippingIssues"
      }
    ]
  }
] });
