<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>HPC Disk Statistics</title>
    <?php require_once("include_php/css.php"); ?>
    <?php require_once("include_php/leading_js.php"); ?>
    <script>
      var tableApp = angular.module('tableApp', ['ui.bootstrap','angular-humanize']);
      tableApp.controller('tableCtrl', function($scope, $http, $location) {
        // Pull URL from user's browser to determine how to query database (useful for SSH tunneling through localhost)
        var site = $location.protocol() + "://" + $location.host() + ":" + $location.port();
        // Determine if we're running development or production version
        if ($location.absUrl().indexOf('diskstatsdev') === -1) {
          var path = "/diskstats/";
        } else {
          var path = "/diskstatsdev/";
        }
        var detailPage = path + "histogram_backend.php";
        var filesysPage = path + "helper_php/filesys.php";
        var ownerPage = path + "helper_php/owner.php";
        console.log("Loading data from: " + site + path);
        onetimeinitialize = function() {
          $scope.filesysOpts = [];
          $scope.ownerOpts = [];
          $scope.warning = false;
          $scope.info = true;
          $scope.numfs = 1;
          $scope.isSummarized = false;
          $scope.numAges = 9;
          $scope.ages = ["<30 Days", "30 - 60 Days", "60 - 90 Days", "90 Days - 6 Months", "6 Months - 1 Year", "1 - 2 Years", "2 - 3 Years", "3 - 5 Years", ">5 Years"];
        }
        reinitialize = function() {
          // Show loading spinners until AJAX returns
          $scope.tableloading = true;
          $scope.progressbarloading = true;
          $scope.result = [];
          $scope.detailedResult = [];
          $scope.summedResult = [];
          $scope.totaledResult = [];
          $scope.grandTotaledResult = {File_System: "All", Age: "Total", Number_of_Files: 0, Size_of_Files: 0};
          $scope.returned = 0;
          $scope.selectedFilesys = $scope.currentFilesys;
        }
        $scope.currentFilesys = "ALL";
        $scope.currentOwner = "";

        function calc_percent(num, total) {
          // Check for NaN condition
          if (total == 0) {
            return 0;
          }
          else { // else calculate the percentage
            return parseFloat(((num / total) * 100).toFixed(2));
          }
        }

        function find_total(fs, value) {
          for (var i = 0; i < $scope.totaledResult.length; i++) {
            if ($scope.totaledResult[i].File_System == fs) {
              return $scope.totaledResult[i][value];
            }
          }
        }

        function checkSumExists(row) {
          index = -1;
          for (var i = 0; i < $scope.summedResult.length; i++) {
            if ($scope.summedResult[i] != null) {
              if ($scope.summedResult[i].Age == row.Age) {
                index = i;
              }
            }
          }
          return index;
        }

        function checkTotalExists(row) {
          index = -1;
          for (var i = 0; i < $scope.totaledResult.length; i++) {
            if ($scope.totaledResult[i].File_System == row.File_System) {
              index = i;
            }
          }
          return index;
        }

        // Copied from http://codegolf.stackexchange.com/questions/17127/array-merge-without-duplicates
        function merge(a1, a2) {
          var hash = {};
          var arr = [];
          for (var i = 0; i < a1.length; i++) {
             if (hash[a1[i]] !== true) {
               hash[a1[i]] = true;
               arr[arr.length] = a1[i];
             }
          }
          for (var i = 0; i < a2.length; i++) {
             if (hash[a2[i]] !== true) {
               hash[a2[i]] = true;
               arr[arr.length] = a2[i];
             }
          }
          return arr;
       }

        $scope.getOptions = function() {
          // Get list of file systems
          $http.get(site + filesysPage).then(function (response) {
            // Successful HTTP GET
            $scope.filesysOpts = response.data;
            $scope.numfs = response.data.length;
            var returnedOptions = 0;
            for (var i = 0; i < $scope.filesysOpts.length; i++) {
              // Get list of owners
              $http.get(site + ownerPage + "?fs=" + $scope.filesysOpts[i]).then(function (response) {
                // Successful HTTP GET
                $scope.ownerOpts = merge($scope.ownerOpts, response.data);
                returnedOptions++;
              }, function (response) {
                // Failed HTTP GET
                console.log("Failed to load page");
              }).finally(function() {
                // Upon success or failure
                if (returnedOptions == $scope.filesysOpts.length) {
                  $scope.filesysOpts.sort();
                  $scope.ownerOpts.sort();
                  $scope.filesysOpts.unshift("ALL");
                }
              });
            }
          }, function (response) {
            // Failed HTTP GET
            console.log("Failed to load page");
          }).finally(function() {
            // Upon success or failure
          });
        }

        $scope.query = function() {
          // Check if the user has provided the necessary inputs
          if ($scope.currentOwner != "") {
            reinitialize();
            $scope.progressbarloading = false;
            $scope.warning = false;
            $scope.info = false;
            // Query for each file system's data
            for (var i = 0; i < $scope.filesysOpts.length; i++) {
              // If the user selected a file system query or proceed of user selected all file systems
              if ($scope.filesysOpts[i] == $scope.selectedFilesys || $scope.selectedFilesys == "ALL") {
                // Find out the number of queries we're going to make
                if ($scope.selectedFilesys == "ALL") {
                  $scope.max = $scope.numfs * $scope.numAges;
                }
                else {
                  $scope.max = $scope.numAges;
                }
                // If the user selected all file systems we want to query for each file system except the one named "ALL"
                if ($scope.filesysOpts[i] != "ALL") {
                  for (var l = 0; l < $scope.numAges; l++) {
                    $http.get(site + detailPage + "?fs=" + $scope.filesysOpts[i] + "&owner=" + $scope.currentOwner + "&id=" + l).then(function (response) {
                      // Successful HTTP GET
                      for (var j = 0; j < response.data.length; j++) {
                        detailedResultRow = response.data[j];
                        // Combine results from each time slot to gather summary data across all file systems
                        sumidx = checkSumExists(detailedResultRow);
                        if (sumidx != -1) {
                          // A row already exists for this owner and age at sumidx so just add to it
                          $scope.summedResult[sumidx].Number_of_Files += detailedResultRow.Number_of_Files;
                          $scope.summedResult[sumidx].Size_of_Files += detailedResultRow.Size_of_Files;
                        }
                        else {
                          // A row does not already exist so create a new summary row
                          // Objects are passed by reference, so we have to make a copy of it using JSON parsing
                          $scope.summedResult[$scope.ages.indexOf(detailedResultRow.Age)] = JSON.parse(JSON.stringify(detailedResultRow));
                        }
                        // Combine results from each file system to gather summary data per file system
                        totalidx = checkTotalExists(detailedResultRow);
                        if (totalidx != -1) {
                          // A row already exists for this owner and file system at totalidx so just add to it
                          $scope.totaledResult[totalidx].Number_of_Files += detailedResultRow.Number_of_Files;
                          $scope.totaledResult[totalidx].Size_of_Files += detailedResultRow.Size_of_Files;
                        }
                        else {
                          // A row does not already exist so create a new total row
                          // Objects are passed by reference, so we have to make a copy of it using JSON parsing
                          tmpobject = JSON.parse(JSON.stringify(detailedResultRow));
                          tmpobject.Age = "Total";
                          $scope.totaledResult.push(tmpobject);
                        }
                        // Combine results across all file systems to get a grand total
                        $scope.grandTotaledResult.Number_of_Files += detailedResultRow.Number_of_Files;
                        $scope.grandTotaledResult.Size_of_Files += detailedResultRow.Size_of_Files;
                        // If owner isn't already in owner options add it
                        //if ($scope.ownerOpts.indexOf(detailedResultRow.Owner) == -1) {
                        //  $scope.ownerOpts.push(detailedResultRow.Owner);
                        //}
                        // Save results to respective arrays
                        $scope.detailedResult[$scope.ages.indexOf(detailedResultRow.Age)] = JSON.parse(JSON.stringify(detailedResultRow));
                      }
                    }, function (response) {
                      // Failed HTTP GET
                      console.log("Failed to load page");
                    }).finally(function() {
                      // Upon success or failure
                      // Store length of resulting list to determine number of pages
                      $scope.returned++;
                      // If this is the last query to return we can handle all the post processing and show the table
                      if ($scope.returned == $scope.max) {
                        // If all file systems were selected add the grand total to the list so we can filter on one list
                        $scope.totaledResult.push($scope.grandTotaledResult);
                        // Now that all results are in, we can calculate percentages
                        // If we're showing a particular file system we can use the detailed data
                        if ($scope.selectedFilesys != "ALL") {
                          console.log("Single file system");
                          for (var k = 0; k < $scope.detailedResult.length; k++) {
                            var total = find_total($scope.detailedResult[k]["File_System"], "Number_of_Files");
                            var percent = calc_percent($scope.detailedResult[k]["Number_of_Files"], total);
                            $scope.detailedResult[k]["Percentage_of_Total_Files"] = percent;
                            total = find_total($scope.detailedResult[k]["File_System"], "Size_of_Files");
                            percent = calc_percent($scope.detailedResult[k]["Size_of_Files"], total);
                            $scope.detailedResult[k]["Percentage_of_Total_Size"] = percent;
                          }
                        }
                        else {
                          console.log("All file systems");
                          for (var k = 0; k < $scope.summedResult.length; k++) {
                            var total = $scope.grandTotaledResult.Number_of_Files;
                            var percent = calc_percent($scope.summedResult[k]["Number_of_Files"], total);
                            $scope.summedResult[k]["Percentage_of_Total_Files"] = percent;
                            total = $scope.grandTotaledResult.Size_of_Files;
                            percent = calc_percent($scope.summedResult[k]["Size_of_Files"], total);
                            $scope.summedResult[k]["Percentage_of_Total_Size"] = percent;
                          }
                        }
                        // Wait to save to result so Angular doesn't try repeating over sparse array
                        if ($scope.selectedFilesys == "ALL") {
                          $scope.result = $scope.summedResult;
                          $scope.isSummarized = true;
                        }
                        else {
                          $scope.result = $scope.detailedResult;
                          $scope.isSummarized = false;
                        }
                        $scope.tableloading = false;
                        console.log("Detailed result");
                        console.log($scope.detailedResult);
                        console.log("Per time slot summary");
                        console.log($scope.summedResult);
                        console.log("Per file system summary");
                        console.log($scope.totaledResult);
                        console.log("Combined file system summary");
                        console.log($scope.grandTotaledResult);
                      }
                    });
                  }
                }
              }
            }
          }
          else {
            $scope.warning = true;
            $scope.info = false;
          }
        }
      onetimeinitialize();
      $scope.getOptions();
      });
    </script>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body ng-app="tableApp" ng-controller="tableCtrl">
    <?php require_once("include_php/navbar.php"); ?>
    <div class="container-fluid ng-cloak">
      <div class="row vertical-align" style="margin-bottom:15px">
        <div class="col-md-3 text-center">
          <form class="form-inline">
            <div class="form-group">
              <label><i class="fa fa-files-o"></i>&nbsp;&nbsp;File System:</label>
              <div class="input-group">
                <select class="form-control" ng-model="currentFilesys" ng-options="opt for opt in filesysOpts"></select>
              </div>
            </div>
          </form>
        </div>
        <div class="col-md-3 text-center">
          <form class="form-inline">
            <div class="form-group">
              <label><i class="fa fa-user"></i>&nbsp;&nbsp;Owner:</label>
              <div class="input-group">
                <select class="form-control" ng-model="currentOwner" ng-options="opt for opt in ownerOpts"></select>
              </div>
            </div>
          </form>
        </div>
        <div class="col-md-3 text-center">
          <form class="form-inline">
            <button type="button" class="btn btn-primary" ng-click="query()"><i class="fa fa-search"></i>&nbsp;&nbsp;Search</button>
          </form>
        </div>
        <div class="col-md-3">
          <div class="text-center">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target=".info-modal">
              <i class="fa fa-info-circle fa-lg"></i>&nbsp;&nbsp;Info
            </button>
          </div>
          <div class="modal fade info-modal" tabindex="-1" role="dialog" aria-labelledby="infoModalLabel">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                  <h4 class="modal-title">About This Page</h4>
                </div>
                <div class="modal-body">
                  <ul>
                    <li>The age of a file is based on the last time it was read.  <b>The HPC strives to have no data older than 6 months old on these file systems.</b></li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="row" ng-show="tableloading && !progressbarloading">
        <div class="col-md-3"></div>
        <div class="col-md-6">
          <uib-progressbar class="progress-striped active" max="max" value="returned"></uib-progressbar>
        </div>
        <div class="col-md-3"></div>
      </div>
      <div class="row" ng-show="warning || info">
        <div class="col-md-3"></div>
        <div class="col-md-6">
          <div class="alert text-center" role="alert" ng-class="{'alert-danger': warning, 'alert-info': info}">
            <b>Please select an owner to continue.</b>
          </div>
        </div>
        <div class="col-md-3"></div>
      </div>
      <div class="row" ng-hide="tableloading">
        <div class="col-md-12"> 
          <div class="table-responsive">
            <table class="table table-striped table-bordered table-ultracondensed table-hover" style="table-layout: fixed">
              <thead>
                <tr class="active">
                  <th ng-repeat="(key,value) in result[0]" ng-hide="isSummarized && key == 'File_System'" style="word-wrap: break-word">
                    <a href="#">
                      {{ key }}
                    </a>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr ng-repeat="row in result">
                  <td class="text-nowrap" ng-hide="isSummarized"><div class="text-nowrap limit-cell">{{ row.File_System }}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Age }}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Number_of_Files | humanizeInt}}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Percentage_of_Total_Files}}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Size_of_Files | humanizeFilesize}}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Percentage_of_Total_Size}}</td>
                </tr>
                <tr class="active">
                  <td class="text-nowrap" ng-repeat="row in totaledResult | filter:{File_System:selectedFilesys}" ng-hide="isSummarized"><div class="text-nowrap limit-cell"><b>{{ row.File_System }}</b></td>
                  <td class="text-nowrap" ng-repeat="row in totaledResult | filter:{File_System:selectedFilesys}"><div class="text-nowrap limit-cell"><b>{{ row.Age }}</b></td>
                  <td class="text-nowrap" ng-repeat="row in totaledResult | filter:{File_System:selectedFilesys}"><div class="text-nowrap limit-cell"><b>{{ row.Number_of_Files | humanizeInt }}</b></td>
                  <td class="text-nowrap" ng-repeat="row in totaledResult | filter:{File_System:selectedFilesys}"><div class="text-nowrap limit-cell"><b></b></td>
                  <td class="text-nowrap" ng-repeat="row in totaledResult | filter:{File_System:selectedFilesys}"><div class="text-nowrap limit-cell"><b>{{ row.Size_of_Files | humanizeFilesize }}</b></td>
                  <td class="text-nowrap" ng-repeat="row in totaledResult | filter:{File_System:selectedFilesys}"><div class="text-nowrap limit-cell"><b></b></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php require_once("include_php/footer.php"); ?>
    <?php require_once("include_php/trailing_js.php"); ?>
  </body>
</html>
