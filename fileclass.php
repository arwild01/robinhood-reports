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
        var detailPage = path + "fileclass_backend.php";
        var filesysPage = path + "helper_php/filesys.php";
        var ownerPage = path + "helper_php/owner.php";
        console.log("Loading data from: " + site + path);
        onetimeinitialize = function() {
          $scope.filesysOpts = [];
          $scope.ownerOpts = [];
          $scope.warning = false;
          $scope.info = true;
          $scope.numfs = 1;
          $scope.numPerPage = 1000;
          $scope.numPerPageOpts = [5, 10, 25, 50, 100, 250, 1000];
          $scope.currentPage = 1;
          $scope.maxSize = 5;
          $scope.tablectrlsloading = true;
        }
        reinitialize = function() {
          // Show loading spinners until AJAX returns
          $scope.tableloading = true;
          $scope.progressbarloading = true;
          $scope.tablectrlsloading = true;
          $scope.result = [];
          $scope.returnedfs = 0;
          $scope.selectedFilesys = $scope.currentFilesys;
          $scope.totalItems = 0;
        }
        $scope.currentFilesys = "ALL";
        $scope.currentOwner = "";
        // Set the default sorting type
        $scope.sortType = "File";
        // Set the default sorting order
        $scope.sortReverse = false;

        $scope.sortChanged = function(key) {
          $scope.sortType = key;
          $scope.sortReverse = !$scope.sortReverse;
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
                // If the user selected all file systems we want to query for each file system except the one named "ALL"
                if ($scope.filesysOpts[i] != "ALL") {
                  // Calculate the offset at which to pull data from the database
                  $http.get(site + detailPage + "?fs=" + $scope.filesysOpts[i] + "&owner=" + $scope.currentOwner + "&page=<?php echo $_GET["page"] ?>").then(function (response) {
                    // Successful HTTP GET
                    $scope.totalItems += response.data.length;
                    for (var j = 0; j < response.data.length; j++) {
                      $scope.result.push(response.data[j]);
                    }
                  }, function (response) {
                    // Failed HTTP GET
                    console.log("Failed to load page");
                  }).finally(function() {
                    // Upon success or failure
                    // Store length of resulting list to determine number of pages
                    $scope.returnedfs++;
                    // If this is the last query to return we can handle all the post processing and show the table
                    if (($scope.returnedfs == $scope.numfs && $scope.selectedFilesys == "ALL") || ($scope.returnedfs == 1 && $scope.selectedFilesys != "ALL")) {
                      $scope.tablectrlsloading = false;
                      $scope.tableloading = false;
                    }
                  });
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
                    <li>The data in the table can be sorted by clicking on each column header.</li>
                    <li>Long results may be split into multiple pages.  Controls for the length of the results and switching pages are available at the bottom of the page.</li>
                    <li>The total number of results is listed at the bottom of the page.</li>
                  <?php
                    if ($_GET["page"] == "interesting") {
                    echo "<li><b>HPC parallel filesystems are not backed up.  Files that cannot be easily recreated or contain intellectual property should be stored on file systems that are backed up.  /hpcdata is a file system that is backed up.</b></li>
                    <li>Files ending in these extensions are included in this list:</li>
                    <ul>
                      <li>.ash</li>
                      <li>.bash</li>
                      <li>.csh</li>
                      <li>.ksh</li>
                      <li>.sh</li>
                      <li>.perl</li>
                      <li>.pl</li>
                      <li>.py</li>
                      <li>.rb</li>
                      <li>.c</li>
                      <li>.f</li>
                      <li>.h</li>
                      <li>.m</li>
                      <li>.cpp</li>
                      <li>.cpx</li>
                      <li>.cxx</li>
                      <li>.hpp</li>
                      <li>.hpx</li>
                      <li>.hxx</li>
                      <li>.f77</li>
                      <li>.f90</li>
                      <li>.jar</li>
                      <li>.java</li>
                      <li>.js</li>
                      <li>makefile</li>
                      <li>Makefile</li>
                      <li>.job</li>
                      <li>.par</li>
                    </ul>";
                  }
                  elseif ($_GET["page"] == "print") {
                    echo "<li><b>After a job has completed, and output checked, print files should be deleted.</b></li>";
                  }
                  ?>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="row" ng-show="tableloading && !progressbarloading">
        <div class="spinner">
          <div class="bounce1"></div>
          <div class="bounce2"></div>
          <div class="bounce3"></div>
        </div>
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
            <table class="table table-striped table-bordered table-ultracondensed table-hover">
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
                <tr ng-repeat="row in result | filter:filterInput | orderBy:sortType:sortReverse | limitTo:numPerPage:(currentPage-1)*numPerPage">
                  <td class="text-nowrap"><div class="text-nowrap">{{ row.File }}</td>
                  <td class="text-nowrap"><div class="text-nowrap">{{ row.Size | humanizeFilesize }}</td>
                  <td class="text-nowrap"><div class="text-nowrap">{{ row.Owner }}</td>
                  <td class="text-nowrap"><div class="text-nowrap">{{ row.Group }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="row vertical-align" ng-hide="tablectrlsloading">
        <div class="col-md-4">
          <div class="text-center">
            <form class="form-inline">
              <label>Show <select class="form-control input-sm" ng-model="numPerPage" ng-options="opt for opt in numPerPageOpts"></select> items</label>
            </form>
          </div>
        </div>
        <div class="col-md-4">
          <div class="text-center">
            <uib-pagination total-items="totalItems" ng-model="currentPage" max-size="maxSize" items-per-page="numPerPage" boundary-links="true"></uib-pagination>
          </div>
        </div>
        <div class="col-md-4">
          <div class="text-center">
            <b>{{ totalItems | humanizeInt }} files found</b>
          </div>
        </div>
      </div>
    </div>
    <?php require_once("include_php/footer.php"); ?>
    <?php require_once("include_php/trailing_js.php"); ?>
  </body>
</html>
