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
    
    //
    // user supplied params that are picked up and populated by the URL parser
    //
    private $suppliedDisplayFields = null;

    private $suppliedLimit = null;

    private $suppliedOffset = null;
    
    // relationship1, relationship2 | all | none
    private $suppliedWith = 'all';
    
    // field1,-field2
    private $suppliedSort = null;
    
    // do i need this?
    private $suppliedSearchFields = null;
    
    //
    // entity supplied list of params that override or merge with URL params
    //
    public $entityAllowedFields = null;

    public $entityBlockFields = array();

    public $entityLimit = 1000;

    public $entityOffset = null;
    
    // relationship1, relationship2 | all | none
    public $entityWith = null;
    
    // field1,-field2
    public $entitySort = null;
    
    // do i need this?
    public $entitySearchFields = null;
    
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
        '_url'
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
    public function __construct(array $allowedFields = null)
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
     * @return string
     */
    public function getWith()
    {
        if (is_null($this->suppliedWith) and is_null($this->entityWith)) {
            return 'none'; // process nothing!
        }
        
        if ($this->entityWith == 'none') {
            return 'none'; // allow entity override
        }
        
        if (! is_null($this->entityWith) and $this->suppliedWith == 'all') {
            return $this->entityWith; // process entity default if supplied is all
        }
        
        if (! is_null($this->entityWith) and ! is_null($this->suppliedWith)){
            $entityWithArray = explode(",", $this->entityWith);
            $suppliedWithArray = explode(",", $this->suppliedWith);
            $newWith = array_unique(array_merge($entityWithArray, $suppliedWithArray));
            return implode(",", $newWith);
        }
        
        if (! is_null($this->suppliedWith) and is_null($this->entityWith)) {
            return $this->suppliedWith; // process supplied default if entity is null
        }
        
        // could just throw a non-fatal error here
        throw new HTTPException("Could not proccess search.", 401, array(
            'dev' => 'Error calculating the correct set of related tables to supply with resource.',
            'internalCode' => '87918906816'
        ));
        
        // set to none for safety, easily reverse by setting entityWith to 'all'
        return 'none';
    }
    
    // placeholder
    public function getAllowedFields()
    {
        if (! isset($this->suppliedDisplayFields) and ! isset($this->entityAllowedFields)) {
            return 'all';
        }
        
        if (isset($this->suppliedDisplayFields)) {
            return $this->suppliedDisplayFields;
        }
        
        if (isset($this->entityDisplayFields)) {
            return $this->entityDisplayFields;
        }
        
        return 'all';
    }

    public function getBlockFields()
    {}

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
            //decode values first
            $fields = $request->get('fields', null, null);            
            $this->parsePartialFields(html_entity_decode($fields));
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
     * Parses out partial fields to return in the response.
     * Unparsed: (id,name,location)
     * Parsed: array('id', 'name', 'location')
     *
     * @param string $unparsed
     *            Unparsed string of fields to return in partial response
     * @return array Array of fields to return in partial response
     */
    protected function parsePartialFields($unparsed)
    {
        $this->isPartial = true;
        $this->suppliedDisplayFields = explode(',', trim($unparsed, '()'));
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
                // add to the list of field searches
                $mapped[$key] = $request->get($key, 'string');
            }
        }
        
        // save the parsed fields to the class
        $this->suppliedSearchFields = $mapped;
    }
}
