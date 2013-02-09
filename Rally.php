<?php

namespace Yahoo\Connectors;

use Exception;

/**
 * Rally API Connector
 *
 * Simple class for interacting with RallyDev web services
 *
 * @version 1.40
 * @author St. John Johnson <stjohn@yahoo-inc.com>
 * @see http://github.com/yahoo/php-rally-connector
 * @copyright Copyright (c) 2013, Yahoo! Inc.  All rights reserved.
 *
 * Redistribution and use of this software in source and binary forms,
 * with or without modification, are permitted provided that the following
 * conditions are met:
 *
 * - Redistributions of source code must retain the above
 *   copyright notice, this list of conditions and the
 *   following disclaimer.
 *
 * - Redistributions in binary form must reproduce the above
 *   copyright notice, this list of conditions and the
 *   following disclaimer in the documentation and/or other
 *   materials provided with the distribution.
 *
 * - Neither the name of Yahoo! Inc. nor the names of its
 *   contributors may be used to endorse or promote products
 *   derived from this software without specific prior
 *   written permission of Yahoo! Inc.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
 * IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED
 * TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
 * PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
class Rally {
  // Curl Object
  private $_curl;
  // Rally's Domain
  private $_domain;
  // Just for debugging
  private $_debug = false;
  // Some fancy user agent here
  private $_agent = 'PHP - Rally Api - 1.4';
  // Current API version
  private $_version = '1.40';
  // Current Workspace
  private $_workspace;
  // These headers are required to get valid JSON responses
  private $_headers_request = array(
    'Content-Type: text/javascript'
  );
  // Silly object translation
  private $_objectTranslation = array(
        'story' => 'hierarchicalrequirement',
    'userstory' => 'hierarchicalrequirement',
      'feature' => 'portfolioitem/feature',
   'initiative' => 'portfolioitem/initiative',
        'theme' => 'portfolioitem/theme',
  );
  // User object
  protected $_user = '';

  /**
   * Create Rally Api Object
   *
   * @param string $username
   *   The username for Rally
   * @param string $password
   *   The password for Rally (probably hunter2)
   * @param string $domain
   *   Override for Domain to talk to
   */
  public function __construct($username, $password, $domain = 'rally1.rallydev.com') {
    $this->_domain = $domain;

    $this->_curl = curl_init();

    $this->_setopt(CURLOPT_RETURNTRANSFER, true);
    $this->_setopt(CURLOPT_HTTPHEADER, $this->_headers_request);
    $this->_setopt(CURLOPT_VERBOSE, $this->_debug);
    $this->_setopt(CURLOPT_USERAGENT, $this->_agent);
    $this->_setopt(CURLOPT_HEADER, 0);
    // Authentication
    $this->_setopt(CURLOPT_USERPWD, "$username:$password");
    $this->_setopt(CURLOPT_HTTPAUTH, CURLAUTH_ANY);

    // Validate Login was Successful
    $user_data = $this->find('user', "(EmailAddress = \"{$username}\")");
    $this->_user = $user_data[0];
  }

  /**
   * Return Reference to User
   *
   * @return string
   *   Reference link to User
   */
  public function me() {
    return $this->_user['_ref'];
  }

  /**
   * Translate object types
   *
   * This is only really for
   *   story -> hierarchicalrequirement
   *
   * @param string $object
   *   Rally Object Type
   * @return string
   *   Translated Object
   */
  protected function _translate($object) {
    $object = strtolower($object);
    if (isset($this->_objectTranslation[$object])) {
      return $this->_objectTranslation[$object];
    }
    return $object;
  }

  /**
   * Set current workspace
   *
   * @param string $workspace_ref
   *   Workspace URL Reference
   */
  public function setWorkspace($workspace_ref) {
    $this->_workspace = $workspace_ref;
  }

  /**
   * Generates a reference URL to the Object
   *
   * @param string $object
   *   Rally Object Type
   * @param int $id
   *   Rally Object ID
   * @return string
   *   Proper URL or _ref to use
   */
  public function getRef($object, $id) {
    $object = $this->_translate($object);
    $ref = "/{$object}";
    if ($id) {
      $ref .= "/{$id}";
    }
    return $ref . '.js';
  }

  /**
   * Find Rally objects
   *
   * This method automatically traverses
   * and strips out Rally API gibbledy-gook
   *
   * @param string $object
   *   Rally Object Type
   * @param string $query
   *   Query String
   * @param string $order
   *   Field to sort on
   * @param bool $fetch
   *   Fetch all content
   * @return array
   *   Returned Objects
   */
  public function find($object, $query, $order = '', $fetch = true) {
    $object = $this->_translate($object);
    $params = array(
      'query' => $query,
      'fetch' => ($fetch ? 'true' : 'false'),
   'pagesize' => 100,
      'start' => 1,
    );
    if (!empty($order)) {
      $params['order'] = $order;
    }

    // Loop through and ask for results
    $results = array();
    for (;;) { // I hate infinite loops
      $objects = $this->_get($this->_addWorkspace("{$object}.js", $params));
      $results = array_merge($results, $objects['Results']);

      // Continue only if there are more
      if ($objects['TotalResultCount'] > 99 + $params['start']) {
        $params['start'] += 100;
        continue;
      }

      // We're done, break
      break;
    }

    return $results;
  }

  /**
   * Get a Rally object
   *
   * @param string $object
   *   Rally Object Type
   * @param int $id
   *   Rally Object ID
   * @return array
   *   Rally API response
   */
  public function get($object, $id) {
    return reset($this->_get($this->_addWorkspace($this->getRef($object, $id))));
  }

  /**
   * Create a Rally object
   *
   * @param string $object
   *   Rally Object Type
   * @param array $params
   *   Fields to create with
   * @return array
   *   Rally API response
   */
  public function create($object, array $params) {
    $url = $this->_addWorkspace($this->getRef($object, 'create'));

    $object = $this->_put($url, $params);
    return $object['Object'];
  }

  /**
   * Update a Rally object
   *
   * @param string $object
   *   Rally Object Type
   * @param int $id
   *   Rally Object ID
   * @param array $params
   *   Fields to update
   * @return array
   *   Rally API response
   */
  public function update($object, $id, array $params) {
    $url = $this->_addWorkspace($this->getRef($object, $id));

    $object = $this->_post($url, $params);
    return $object['Object'];
  }

  /**
   * Delete a Rally object
   *
   * @param string $object
   *   Rally Object Type
   * @param int $id
   *   Rally Object ID
   * @return bool
   */
  public function delete($object, $id) {
    $url = $this->_addWorkspace($this->getRef($object, $id));

    // There are no values that return here
    $this->_delete($this->getRef($url, $id));
    return true;
  }

  /**
   * Wraps Workspace around URL
   *
   * @param string $url
   *   URL to access
   * @param array $params
   *   Any additional parameters to put on the Query String
   */
  protected function _addWorkspace($url, array $params = array()) {
    // Add workspace
    if ($this->_workspace) {
      $params['workspace'] = $this->_workspace;
    }

    // Add params as url
    if (!empty($params)) {
      $url .= '?' . http_build_query($params);
    }

    return $url;
  }

  /**
   * Perform a HTTP Get
   *
   * @param string $method
   *   Method of the API to execute
   * @return array
   *   API return data
   */
  protected function _get($method) {
    $this->_setopt(CURLOPT_CUSTOMREQUEST, 'GET');
    $this->_setopt(CURLOPT_POSTFIELDS, '');

    return $this->_execute($method);
  }

  /**
   * Perform a HTTP Post
   *
   * @param string $method
   *   Method of the API to execute
   * @param array $params
   *   Paramters to pass
   * @return array
   *   API return data
   */
  protected function _post($method, array $params = array()) {
    $this->_setopt(CURLOPT_CUSTOMREQUEST, 'POST');

    $payload = json_encode(array('Content' => $params));
    $this->_setopt(CURLOPT_POSTFIELDS, $payload);

    return $this->_execute($method);
  }

  /**
   * Perform a HTTP Put
   *
   * @param string $method
   *   Method of the API to execute
   * @param array $params
   *   Paramters to pass
   * @return array
   *   API return data
   */
  protected function _put($method, array $params = array()) {
    $this->_setopt(CURLOPT_CUSTOMREQUEST, 'PUT');

    $payload = json_encode(array('Content' => $params));
    $this->_setopt(CURLOPT_POSTFIELDS, $payload);

    return $this->_execute($method);
  }

  /**
   * Perform a HTTP Delete
   *
   * @param string $method
   *   Method of the API to execute
   * @return array
   *   API return data
   */
  protected function _delete($method) {
    $this->_setopt(CURLOPT_CUSTOMREQUEST, 'DELETE');

    return $this->_execute($method);
  }

  /**
   * Execute the Curl object
   *
   * @param string $method
   *   Method of the API to execute
   * @return array
   *   API return data
   * @throws RallyApiException
   *   On Curl errors
   */
  protected function _execute($method) {
    $method = ltrim($method, '/');
    $url = "https://{$this->_domain}/slm/webservice/{$this->_version}/{$method}";

    $this->_setopt(CURLOPT_URL, $url);

    $response = curl_exec($this->_curl);

    if (curl_errno($this->_curl)) {
     throw new RallyApiException(curl_error($this->_curl));
    }

    $info = curl_getinfo($this->_curl);

    return $this->_result($response, $info);
  }

  /**
   * Perform Json Decryption of the output
   *
   * @param string $response
   *   Curl Response
   * @param array $info
   *   Curl Info Array
   * @return array
   *   API return data
   * @throws RallyApiException
   *   On non-2xx responses
   */
  protected function _result($response, array $info) {
    // Panic on non-200 responses
    if ($info['http_code'] >= 300 || $info['http_code'] < 200) {
      throw new RallyApiException($response, $info['http_code']);
    }

    $object = json_decode($response, true);

    $wrappers = array('OperationResult','CreateResult','QueryResult');
    // If we have one of these formats, strip out errors
    if (in_array(key($object), $wrappers)) {
      // Strip key
      $object = reset($object);

      // Look for errors and warnings
      if (!empty($object['Errors'])) {
        throw new RallyApiError(implode(PHP_EOL, $object['Errors']));
      }
      if (!empty($object['Warnings'])) {
        throw new RallyApiWarning(implode(PHP_EOL, $object['Warnings']));
      }
    }

    return $object;
  }

  /**
   * Wrapper for curp_setopt
   *
   * @param string $option
   *   the CURLOPT_XXX option to set
   * @param varied $value
   *   the value
   */
  protected function _setopt($option, $value) {
    curl_setopt($this->_curl, $option, $value);
  }
}

class RallyApiException extends Exception { }
class RallyApiError extends RallyApiException { }
class RallyApiWarning extends RallyApiException { }
