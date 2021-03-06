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
        // Show loading spinners until AJAX returns
        $scope.tableloading = true;
        $scope.progressbarloading = true;
        // Pull URL from user's browser to determine how to query database (useful for SSH tunneling through localhost)
        var site = $location.protocol() + "://" + $location.host() + ":" + $location.port();
        // Determine if we're running development or production version
        if ($location.absUrl().indexOf('diskstatsdev') === -1) {
          var path = "/diskstats/";
        } else {
          var path = "/diskstatsdev/";
        }
        var sumPage = path + "summary_backend.php";
        var filesysPage = path + "helper_php/filesys.php";
        console.log("Loading data from: " + site + path);
        $scope.filesys = [];
        $scope.result = [];
        $scope.numfs = 0;
        $scope.returnedfs = 0;
        // Set the default sorting type
        $scope.sortType = "File_System";
        // Set the default sorting order
        $scope.sortReverse = false;

        $scope.sortChanged = function(key) {
          $scope.sortType = key;
          $scope.sortReverse = !$scope.sortReverse;
        }

        $scope.query = function() {
          $scope.tableloading = true;
          $scope.result = [];
          // Get list of file systems
          $http.get(site + filesysPage).then(function (response) {
            // Successful HTTP GET
            $scope.filesys = response.data;
            $scope.numfs = response.data.length;
            $scope.progressbarloading = false;
            console.log($scope.filesys);
            for (var i = 0; i < $scope.filesys.length; i++) {
              // Query for each file system's data
              $http.get(site + sumPage + "?fs=" + $scope.filesys[i]).then(function (response) {
                // Successful HTTP GET
                $scope.result.push(response.data[0]);
                // Store length of resulting list to determine number of pages
              }, function (response) {
                // Failed HTTP GET
                console.log("Failed to load page");
              }).finally(function() {
                // Upon success or failure
                $scope.returnedfs++;
                $scope.tableloading = false;
                if ($scope.returnedfs == $scope.numfs) {
                  $scope.progressbarloading = true;
                  console.log($scope.result);
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
        $scope.query();
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
      <div class="row" ng-hide="progressbarloading">
        <div class="col-md-3"></div>
        <div class="col-md-6">
          <uib-progressbar class="progress-striped active" max="numfs" value="returnedfs">{{returnedfs}}/{{numfs}} File Systems</uib-progressbar>
        </div>
        <div class="col-md-3"></div>
      </div>
      <div class="row" ng-hide="tableloading">
        <div class="col-md-12"> 
          <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
              <thead>
                <tr class="active">
                  <th ng-repeat="(key,value) in result[0]" style="word-wrap: break-word">
                    <a href="#" ng-click='sortChanged(key)'>
                      {{ key }}
                      <span ng-show="sortType == key && sortReverse" class="caret"></span>
                      <span class="dropup"><span ng-show="sortType == key && !sortReverse" class="caret"></span></span>
                    </a>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr ng-repeat="row in result | orderBy:sortType:sortReverse">
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.File_System }}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Number_of_Users | humanizeInt}}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Total_Space | humanizeFilesize}}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Used_Space | humanizeFilesize}}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Available_Space | humanizeFilesize}}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Number_of_Files | humanizeInt}}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Size_of_Old_Files | humanizeFilesize }}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Number_of_Old_Files | humanizeInt}}</td>
                  <td class="text-nowrap"><div class="text-nowrap limit-cell">{{ row.Percent_Old_Space }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="row vertical-align" ng-hide="tableloading">
        <div class="col-md-12">
          <div class="pull-right">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target=".info-modal">
              <i class="fa fa-info-circle fa-lg"></i>&nbsp;&nbsp;Info
            </button>
            <div class="modal fade info-modal" tabindex="-1" role="dialog" aria-labelledby="infoModalLabel">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">About This Page</h4>
                  </div>
                  <div class="modal-body">
                    <ul>
                      <li>The data in the table can be sorted by clicking on each column header.</li>
                      <li>An "old" file has not been read in the past 6 months.  <b>The HPC strives to have no data older than 6 months old on these file systems.</b></li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php require_once("include_php/footer.php"); ?>
    <?php require_once("include_php/trailing_js.php"); ?>
  </body>
</html>
