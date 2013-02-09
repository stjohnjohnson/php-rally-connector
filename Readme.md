Rally API Connector
===================

RallyDev, the Agile tool, has pretty decent [webservices][1], but there were no published libraries for talking to them through PHP.  This is a simple but effective class to communicate back and forth with Rally.

## Installation ##

Requires PHP 5.3+ with JSON and CuRL support installed.

## Usage ##

There are only five (5) basic methods you should be using:
 - find (Find list of objects)
 - get (Get an object)
 - create (Create an object)
 - update (Update an object)
 - delete (Delete an object)

Here are some examples:

```php
use Yahoo\Connectors\Rally;

$rally = new Rally($username, $password);
// Set workspace (if you have multiple workspaces)
$rally->setWorkspace($workspace_ref);

// Create
$result = $rally->create('defect', array(
         'Name' => 'New Defect',
  'Description' => 'This is a description',
));
$defect_id = $result['CreateResult']['Object']['ObjectID'];

// Update
$result = $rally->update('defect', $defect_id, array(
  'Name' => 'Something else'
));

// Get
$result = $rally->get('defect', $defect_id);

// Find
$results = $rally->find('defect', '(Name = "Something else")', 'Rank');

// Delete
$rally->delete('defect', $defect_id);

// Me
$result = $rally->update('defect', $defect_id, array(
  'Owner' => $rally->me()
));

```

## Versions ##

 - 1.4 (2013.02.05): Adding method to read user reference $obj->me()
 - 1.3 (2013.01.29): Adding workspace calls to all methods
 - 1.2 (2013.01.24): Allowing you to specify different workspaces
 - 1.1 (2013.01.17): Changing returns to be more stripped down and throwing custom exceptions on errors and warnings
 - 1.0 (2013.01.16): Inital release

## References ##

 - [Rally Web Service Documentation][1]

## License ##

Copyright (c) 2013 Yahoo! Inc.  All rights reserved. Copyrights licensed under the New BSD License.

*St. John Johnson <stjohn@yahoo-inc.com>*

[1]: https://rally1.rallydev.com/slm/doc/webservice/ "Rally Web Service Documentation"
