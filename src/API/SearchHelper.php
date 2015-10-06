<?php
namespace PhalconRest\API;

use \PhalconRest\Util\HTTPException;

/**
 * Assist Entity to query database and shape the response object
 * Will sit at the intersection of Entity field filters & URL supplied values
 *
 * Build query params for Phalcon Model
 * Only designed to be used within the context of a URL request for data
 */
class SearchHelper
{

    public $suppliedLimit = null;

    public $suppliedOffset = null;

    /**
     * can be any of the following:
     * comma, seperated, list
     * requested relationships that may only be overridden by the entity if relationships are blocked
     *
     * default
     * the default that simply takes what the entity would provide
     *
     * all
     * asks for all available relationships
     *
     * none
     * side load no relationships
     */
    public $suppliedWith = 'default';
    
    // field1,-field2
    public $suppliedSort = null;

    /**
     * the maximum number of primary records to be returned by the api in any given call
     *
     * @var int
     */
    public $entityLimit = 500;

    /**
     * used for paginating results but kindof dumb to set from the server side, why not leave it only for the client to request?
     *
     * @var int
     */
    public $entityOffset = null;

    /**
     * can be any of the following
     *
     * block
     * prevent any relationships from being sideloaded regardless
     * of what the client asked for
     *
     * comma, seperated, list
     * a list of supplied relationships
     *
     * all
     * provide all available relationships
     *
     * none
     * entity makes provides no relationships but can be overridden by client
     *
     * entity suggested relationships are only provided if nothing specific is requested by the client
     *
     * @var string
     */
    public $entityWith = 'none';
    
    // a default sort order that could be over ridden by a client submitted value
    // ie. field1,-field2
    public $entitySort = null;
    
    // a list of fields that are reserved in order to process other search related searches
    // this only applies to GET requests
    private $reservedWords = array(
        'with',
        'sort',
        'sortField',
        'offset',
        'limit',
        'fields',
        'sort_field',
        'perPage',
        'per_page',
        'page',
        'type',
        '_url',
        'order'
    );
    // added since it seems to be included with some installs
    
    /**
     * If query string contains 'q' parameter.
     * This indicates the request is searching an entity
     *
     * @var boolean
     */
    protected $isSearch = false;

    /**
     * If query contains 'fields' parameter.
     * This indicates the request wants back only certain fields from a record
     *
     * @var boolean
     */
    public $isPartial = false;

    /**
     * should the request include pager information?
     * set to false by default since it's possible the Entity may ask for it
     *
     * @var boolean
     */
    public $isPager = true;

    /**
     * store a list of relationships to be loaded
     * possible values:
     * csv list of relationships to load (user_addrs,user_numbers)
     * all means load all available.
     * auto means load whatever is already configured
     * none means load no relationships unless an override is present
     *
     * @var string
     */
    public $relationships = null;

    /**
     * load the DI, is this the best way ?
     */
    public function __construct()
    {
        $di = \Phalcon\DI::getDefault();
        $this->di = $di;
        
        // close enough, let's parse the inputs since we assume searchHelper is always called within the context of a query
        $this->parseRequest();
    }

    /**
     * return the correct limit based on set order
     * supplied, entity, none
     *
     * @return mixed
     */
    public function getLimit()
    {
        if (! is_null($this->suppliedLimit)) {
            return $this->suppliedLimit;
        } elseif (! is_null($this->entityLimit)) {
            return $this->entityLimit;
        }
        return false;
    }

    /**
     * return the correct offset based on set order
     * supplied, entity, none
     *
     * @return mixed
     */
    public function getOffset()
    {
        if (! is_null($this->suppliedOffset)) {
            return $this->suppliedOffset;
        } elseif (! is_null($this->entityOffset)) {
            return $this->entityOffset;
        }
        return false;
    }

    /**
     * return the correct sort based on set order
     * supplied, entity, none
     *
     * @param string $format
     *            return the native string format or a sql version instead?
     * @return mixed mixed return false when no sort if found, otherwise a string in native|sql format
     */
    public function getSort($format = 'native')
    {
        if (! is_null($this->suppliedSort)) {
            $nativeSort = $this->suppliedSort;
        } elseif (! is_null($this->entitySort)) {
            $nativeSort = $this->entitySort;
        } else {
            // no explicit sort supplied, return false
            return false;
        }
        
        // convert to sql dialect if asked
        if ($format == 'sql') {
            // first_name,-last_name
            $sortFields = explode(',', $nativeSort);
            $parsedSorts = array(); // the final Phalcon friendly sort array
            
            foreach ($sortFields as $order) {
                if (substr($order, 0, 1) == '-') {
                    $subOrder = substr($order, 1);
                    $parsedSorts[] = $subOrder . " DESC";
                } else {
                    $parsedSorts[] = $order;
                }
            }
            $nativeSort = implode(',', $parsedSorts);
        }
        
        return $nativeSort;
    }

    /**
     * return the correct set of related tables to include
     *
     * client: default, all, none, csv
     * entity: block, all, none, csv
     *
     * client: all, none, csv
     *
     * @return string
     */
    public function getWith()
    {
        // protect entity if it demands that nothing be sideloaded
        if ($this->entityWith == 'block') {
            return 'none';
        }
        
        // put entity in charge if client defers
        if ($this->suppliedWith == 'default') {
            return $this->entityWith;
        }
        
        // nothing left to do but return what the client asked for
        return $this->suppliedWith;
    }

    /**
     * entity search fields are always applied and should not be modified by suppliedSearchFields
     *
     * @return multitype:NULL |boolean
     */
    public function getSearchFields()
    {
        $searchFields = array();
        
        // return false if nothing is specified
        if (! isset($this->entitySearchFields) and ! isset($this->suppliedSearchFields)) {
            return false;
        }
        
        // list supplied first so it will get overwritten by entity
        $sources = array(
            'suppliedSearchFields',
            'entitySearchFields'
        );
        
        foreach ($sources as $source) {
            if (isset($this->$source)) {
                foreach ($this->$source as $key => $value) {
                    $searchFields[$key] = $value;
                }
            }
        }
        
        return $searchFields;
    }

    /**
     * Main method for parsing a query string.
     * Finds search paramters, partial response fields, limits, and offsets.
     *
     * @return void
     */
    protected function parseRequest()
    {
        // pull various supported inputs from post
        $request = $this->di->get('request');
        
        // only process if it is a get
        if ($request->isGet() == false) {
            return;
        }
        
        // simple stuff first
        $with = $request->get('with', "string", null);
        if (! is_null($with)) {
            $this->suppliedWith = $with;
        }
        
        // load possible sort values in the following order
        // be sure to mark this as a paginated result set
        if ($request->get('sort', "string", null) != NULL) {
            $this->suppliedSort = $request->get('sort', "string", null);
            $this->isPager = true;
        } elseif ($request->get('sort_field', "string", null) != NULL) {
            $this->suppliedSort = $request->get('sort_field', "string", null);
            $this->isPager = true;
        } elseif ($request->get('sortField', "string", null) != NULL) {
            $this->suppliedSort = $request->get('sortField', "string", null);
            $this->isPager = true;
        }
        
        // prep for the harder stuff
        // ?page=1&perPage=25&orderAscending=false
        
        // load possible limit values in the following order
        // be sure to mark this as a paginated result set
        if ($request->get('limit', "string", null) != NULL) {
            $this->suppliedLimit = $request->get('limit', "string", null);
            $this->isPager = true;
        } elseif ($request->get('per_page', "string", null) != NULL) {
            $this->suppliedLimit = $request->get('per_page', "string", null);
            $this->isPager = true;
        } elseif ($request->get('perPage', "string", null) != NULL) {
            $this->suppliedLimit = $request->get('perPage', "string", null);
            $this->isPager = true;
        }
        
        // look for string that means to show all records
        if ($this->isPager) {
            if ($this->suppliedLimit == 'all') {
                $this->suppliedLimit = 9999999999;
            }
        }
        
        // load offset values in the following order
        // Notice that a page is treated differently than offset
        // $this->offset = ($offset != null) ? $offset : $this->offset;
        if ($request->get('offset', "int", null) != NULL) {
            $this->suppliedOffset = $request->get('offset', "int", null);
            $this->isPager = true;
        } elseif ($request->get('page', "int", null) != NULL) {
            $this->suppliedOffset = ($request->get('page', "int", null) - 1) * $this->suppliedLimit;
            $this->isPager = true;
        }
        
        // http://jsonapi.org/format/#fetching
        $this->parseSearchParameters($request);
        
        // If there's a 'fields' parameter
        if ($request->get('fields', null, null)) {
            $this->parsePartialFields($request->get('fields', null, null));
        }
    }

    /**
     * will apply the configured search parameters and build an array for phalcon consumption
     *
     * @return array
     */
    public function buildSearchParameters()
    {
        $search_parameters = array();
        
        if ($this->isSearch) {
            // format for a search
            $approved_search = array();
            foreach ($this->getSearchFields() as $field => $value) {
                // if we spot a wild card, convert to LIKE
                if (strstr($value, '*')) {
                    $value = str_replace('*', '%', $value);
                    $approved_search[] = "$field LIKE '$value'";
                } else {
                    $approved_search[] = "$field='$value'";
                }
            }
            // implode as specified by phalcon
            $search_parameters = array(
                implode(' and ', $approved_search)
            );
        }
        
        $limit = $this->getLimit();
        if ($limit) {
            $search_parameters['limit'] = $limit;
        }
        
        $offset = $this->getOffset();
        if ($offset) {
            $search_parameters['offset'] = $offset;
        }
        
        // if (is_array($this->partialFields))
        // $search_parameters['columns'] = $this->partialFields;
        // // $search_parameters['columns'] = implode(',', $this->partialFields);
        
        $sort = $this->getSort('sql');
        if ($sort) {
            // first_name,-last_name
            $search_parameters['order'] = $sort;
        }
        
        return $search_parameters;
    }

    /**
     * Parses out the search parameters from a request.
     * Will process all URL encoded variable except those on the exception list
     * Unparsed, they will look like this:
     * (name:Benjamin Franklin,location:Philadelphia)
     * subjects&limit=100&offset=9&name=Franklin&location=Philadelphia
     * Parsed:
     * array('name'=>'Franklin', 'location'=>'Philadelphia')
     *
     * @param object $request
     *            Unparsed search string
     * @return void
     */
    protected function parseSearchParameters($request)
    {
        $allFields = $request->get();
        
        $this->isSearch = true;
        
        $mapped = array();
        
        // Split the strings at their colon, set left to key, and right to value.
        foreach ($allFields as $key => $value) {
            
            if (in_array($key, $this->reservedWords)) {
                // ignore, it is reserved
            } else {                
                // sanitize fails for < or <=, even html encoded version
                // insert exception for < value
                $sanitizedValue = $request->get($key, 'string');
                if (strlen($sanitizedValue) == 0 and substr($value, 0, 1) == '<') {
                    $mapped[$key] = $value;
                } else {
                    $mapped[$key] = $sanitizedValue;
                }
            }
        }
        
        // save the parsed fields to the class
        $this->suppliedSearchFields = $mapped;
    }
}
