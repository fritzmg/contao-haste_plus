<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2016 Heimrich & Hannot GmbH
 *
 * @package haste_plus
 * @author  Rico Kaltofen <r.kaltofen@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */

namespace HeimrichHannot\Haste\Map;

use Contao\FilesModel;
use Contao\Validator;

class GoogleMapOverlay
{
    protected $arrOptions = [];

    const TYPE_MARKER         = 'MARKER';
    const TYPE_INFOWINDOW     = 'INFOWINDOW';
    const TYPE_POLYLINE       = 'POLYLINE';
    const TYPE_POLYGON        = 'POLYGON';
    const TYPE_GROUND_OVERLAY = 'GROUND_OVERLAY';
    const TYPE_RECTANGLE      = 'RECTANGLE';
    const TYPE_CIRCLE         = 'CIRCLE';
    const TYPE_KML            = 'KML';
    const TYPE_KML_GEOXML     = 'KML_GEOXML';

    const MARKERTYPE_SIMPLE = 'SIMPLE';
    const MARKERTYPE_ICON   = 'ICON';

    const MARKERACTION_NONE  = 'NONE';
    const MARKERACTION_LINK  = 'LINK';
    const MARKERACTION_INFO  = 'INFO';
    const MARKERACTION_MODAL = 'MODAL';

    private static $iconUrlCache;
    private static $shadowUrlCache;
    private static $overlayUrlCache;

    public function __construct()
    {
        if (!static::init())
        {
            throw new \Exception('dlh_googlemaps module is not enabled');
        }

        $this->prepare();
    }

    public function generate(array $arrOptions = [])
    {
        $this->arrOptions = array_merge($this->arrOptions, $arrOptions);

        $arrData = $this->getData($this->arrOptions);

        $strTemplate = sprintf('dlh_%s', $arrData['customTpl'] ?: strtolower($arrData['type']));

        $objTemplate          = new \FrontendTemplate($strTemplate);
        $objTemplate->map     = $arrData['map'];
        $objTemplate->element = $arrData;

        return $objTemplate->parse();
    }

    public function generateStatic(array $arrOptions = [])
    {
        $this->arrOptions = array_merge($this->arrOptions, $arrOptions);

        $arrData = $this->getData($this->arrOptions);

        $strMarker = '';

        switch ($arrData['type'])
        {
            case 'MARKER':
                if ($arrData['markerType'] == 'ICON')
                {
                    if(isset(static::$iconUrlCache[$arrData['iconSRC']]))
                    {
                        $arrData['iconSRC'] = static::$iconUrlCache[$arrData['iconSRC']];
                    }
                    else if($arrData['iconSRC'] > 0  && null !== ($filesModel = \FilesModel::findByUuid($arrData['iconSRC'])))
                    {
                        static::$iconUrlCache[$arrData['iconSRC']] = $filesModel->path;
                        $arrData['iconSRC'] = static::$iconUrlCache[$arrData['iconSRC']];
                    }

                    return ['icon:' . rawurlencode(\Environment::get('base') . $arrData['iconSRC']) . '|shadow:false|' => $arrData['singleCoords']];
                }
                else
                {
                    $strMarker = '&amp;markers=' . $arrData['singleCoords'];
                }
                break;
            case 'POLYLINE':
                if (is_array($arrData['multiCoords']) && count($arrData['multiCoords']) > 0)
                {
                    $strMarker .= '&amp;path=weight:' . $arrData['strokeWeight']['value'] . '|color:0x' . $arrData['strokeColor'] . dechex(
                            $arrData['strokeOpacity'] * 255
                        );
                    foreach ($arrData['multiCoords'] as $coords)
                    {
                        $strMarker .= '|' . str_replace(' ', '', $coords);
                    }
                }
                break;
            case 'POLYGON':
                if (is_array($arrData['multiCoords']) && count($arrData['multiCoords']) > 0)
                {
                    $strMarker .= '&amp;path=weight:' . $arrData['strokeWeight']['value'] . '|color:0x' . $arrData['strokeColor'] . dechex(
                            $arrData['strokeOpacity'] * 255
                        ) . '|fillcolor:0x' . $arrData['fillColor'] . dechex($arrData['fillOpacity'] * 255);
                    foreach ($arrData['multiCoords'] as $coords)
                    {
                        $strMarker .= '|' . str_replace(' ', '', $coords);
                    }
                    $strMarker .= '|' . str_replace(' ', '', $arrData['multiCoords'][0]);
                }
                break;
        }

        return $strMarker;
    }

    protected function getData(array $arrData)
    {
        $arrData['singleCoords'] = str_replace(' ', '', $arrData['singleCoords']);

        $arrData['multiCoords'] = deserialize($arrData['multiCoords']);
        if (is_array($arrData['multiCoords']))
        {
            $tmp1 = [];
            foreach ($arrData['multiCoords'] as $coords)
            {
                $tmp2      = explode(',', $coords);
                $tmp1[0][] = $tmp2[0];
                $tmp1[1][] = $tmp2[1];
            }
            $arrData['windowPosition'] = array_sum($tmp1[0]) / sizeof($tmp1[0]) . ',' . array_sum($tmp1[1]) / sizeof($tmp1[1]);
        }

        $arrData['iconSize'] = deserialize($arrData['iconSize']);

        $arrData['iconAnchor'] = deserialize($arrData['iconAnchor']);

        if (!$arrData['iconAnchor'][0] || $arrData['iconAnchor'][0] == 0)
        {
            $arrData['iconAnchor'][0] = floor($arrData['iconSize'][0] / 2);
        }
        else
        {
            $arrData['iconAnchor'][0] = floor($arrData['iconSize'][0] / 2) + $arrData['iconAnchor'][0];
        }

        if (!$arrData['iconAnchor'][1] || $arrData['iconAnchor'][1] == 0)
        {
            $arrData['iconAnchor'][1] = floor($arrData['iconSize'][1] / 2);
        }
        else
        {
            $arrData['iconAnchor'][1] = floor($arrData['iconSize'][1] / 2) + $arrData['iconAnchor'][1];
        }

        if($arrData['overlaySRC'] > 0)
        {
            $objFile               = \FilesModel::findByPk($arrData['overlaySRC']);
            $arrData['overlaySRC'] = $objFile->path;
        }

        if(isset(static::$overlayUrlCache[$arrData['overlaySRC']]))
        {
            $arrData['overlaySRC'] = static::$overlayUrlCache[$arrData['overlaySRC']];
        }
        else if($arrData['overlaySRC'] > 0 && null !== ($filesModel = \FilesModel::findByPk($arrData['overlaySRC']))){
            static::$overlayUrlCache[$arrData['overlaySRC']] = $filesModel->path;
            $arrData['overlaySRC'] = static::$overlayUrlCache[$arrData['overlaySRC']];
        }

        if(isset(static::$shadowUrlCache[$arrData['shadowSRC']]))
        {
            $arrData['shadowSRC'] = static::$shadowUrlCache[$arrData['shadowSRC']];
        }
        else if($arrData['shadowSRC'] > 0 && null !== ($filesModel = \FilesModel::findByPk($arrData['shadowSRC']))){
            static::$shadowUrlCache[$arrData['shadowSRC']] = $filesModel->path;
            $arrData['shadowSRC'] = static::$shadowUrlCache[$arrData['shadowSRC']];
        }

        $arrData['shadowSize'] = deserialize($arrData['shadowSize']);

        $arrData['strokeWeight'] = deserialize($arrData['strokeWeight']);

        $tmp1                     = deserialize($arrData['strokeOpacity']);
        $arrData['strokeOpacity'] = $tmp1 / 100;

        $tmp1                   = deserialize($arrData['fillOpacity']);
        $arrData['fillOpacity'] = $tmp1 / 100;

        $arrData['radius'] = deserialize($arrData['radius']);

        if ($arrData['bounds'])
        {
            $arrData['bounds']    = deserialize($arrData['bounds']);
            $tmp1                 = explode(',', $arrData['bounds'][0]);
            $tmp2                 = explode(',', $arrData['bounds'][1]);
            $arrData['bounds'][2] = (trim($tmp1[0]) . trim($tmp2[0])) / 2 . ',' . (trim($tmp1[1]) . trim($tmp2[1])) / 2;
        }

        // important: escape front slashes for js (/ -> \/)
        $arrData['infoWindow'] = preg_replace(
            '/[\n\r\t]+/i',
            '',
            str_replace('/', '\/', addslashes(stripslashes(html_entity_decode(\Controller::replaceInsertTags($arrData['infoWindow'])))))
        );

        $arrData['infoWindowAnchor']    = deserialize($arrData['infoWindowAnchor']);
        $arrData['infoWindowAnchor'][0] = $arrData['infoWindowAnchor'][0] ? -1 * $arrData['infoWindowAnchor'][0] : 0;
        $arrData['infoWindowAnchor'][1] = $arrData['infoWindowAnchor'][1] ? -1 * $arrData['infoWindowAnchor'][1] : 0;

        $tmpSize = deserialize($arrData['infoWindowSize']);

        $arrData['infoWindowSize'] = '';
        if (is_array($tmpSize) && $tmpSize[0] > 0 && $tmpSize[1] > 0)
        {
            $arrData['infoWindowSize'] = sprintf(' style="width:%spx;height:%spx;"', $tmpSize[0], $tmpSize[1]);
        }

        $arrData['routingAddress'] = str_replace(
            '\"',
            '"',
            addslashes(
                str_replace(
                    '
',
                    '',
                    $arrData['routingAddress']
                )
            )
        );
        $arrData['labels']         = $GLOBALS['TL_LANG']['dlh_googlemaps']['labels'];

        $arrData['staticMapPart'] = '';

        //supporting insertags
        $arrData['kmlUrl'] = \Controller::replaceInsertTags($arrData['kmlUrl'], false);

        if(Validator::isUuid($arrData['kmlUrl']))
        {
            $objFile = FilesModel::findByUuid($arrData['kmlUrl']);
        }else if($arrData['kmlUrl'] > 0){
            $objFile           = FilesModel::findByPk($arrData['kmlUrl']);
        }

        if(null !== $objFile){
            $arrData['kmlUrl'] = $objFile->path;
        }

        return $arrData;
    }


    protected function isAvailable()
    {
        return in_array('dlh_googlemaps', \ModuleLoader::getActive());
    }

    protected function prepare(array $arrOptions = [])
    {
        $arrDefaults = [
            'map'                    => '', // the id of the GoogleMap
            'infoWindowUnique'       => false,
            'id'                     => rand(10000, 99999),
            'customTpl'              => '',
            'type'                   => 'MARKER',
            'typesAvailable'         => ['MARKER', 'INFOWINDOW', 'POLYLINE', 'POLYGON', 'GROUND_OVERLAY', 'RECTANGLE', 'CIRCLE', 'KML'],
            'singleCoords'           => '',
            'markerType'             => 'SIMPLE',
            'markerTypesAvailable'   => ['SIMPLE', 'ICON'],
            'markerAction'           => 'NONE',
            'markerActionsAvailable' => ['NONE', 'LINK', 'INFO'],
            'multiCoords'            => null,
            'markerShowTitle'        => true,
            'overlaySRC'             => null,
            'iconSRC'                => null,
            'iconSize'               => [16, 16, 'px'],
            'iconAnchor'             => [0, 0, 'px'],
            'hasShadow'              => '',
            'shadowSize'             => [32, 32, 'px'],
            'strokeColor'            => '000000',
            'strokeOpacity'          => 100,
            'strokeWeight'           => 1,
            'fillColor'              => '',
            'fillOpacity'            => 100,
            'radius'                 => 1000,
            'bounds'                 => '',
            'zIndex'                 => 1,
            'popupInfoWindow'        => false,
            'useRouting'             => false,
            'routingAddress'         => '',
            'infoWindow'             => '',
            'infoWindowSize'         => [320, 160, 'px'],
            'infoWindowAnchor'       => [0, 0, 'px'],
            'infoWindowMaxWidth'     => '',
            'url'                    => '',
            'target'                 => '',
            'linkTitle'              => '',
            'parameter'              => '',
            'kmlUrl'                 => '',
            'kmlClickable'           => true,
            'kmlPreserveViewport'    => false,
            'kmlScreenOverlays'      => true,
            'kmlSuppressInfowindows' => false,
            'inverted'               => false,
            'published'              => true
        ];

        $this->arrOptions = array_merge($arrDefaults, $arrOptions);
    }

    public function getId()
    {
        return $this->arrOptions['id'];
    }

    /**
     * @param $strCoordinates the coordinates where to route to
     *
     * @return $this
     */
    public function setRoute($strCoordinates)
    {
        $this->routingAddress = $strCoordinates;
        $this->markerAction   = 'INFO';

        return $this;
    }

    public function setTitle($strTitle)
    {
        $this->title           = $strTitle;
        $this->markerShowTitle = true;

        return $this;
    }

    public function setPosition($strCoordinates)
    {
        $this->singleCoords = $strCoordinates;

        return $this;
    }

    public function setPositions($arrCoordinates)
    {
        $this->multiCoords = $arrCoordinates;

        return $this;
    }

    public function setMarkerType($strMarkerType)
    {
        $this->markerType = $strMarkerType;

        return $this;
    }

    public function setType($strType)
    {
        $this->type = $strType;

        return $this;
    }

    public function setIconSRC($strIconSrc)
    {
        $this->iconSRC = $strIconSrc;

        return $this;
    }

    public function setIconSize($strIconSize)
    {
        $this->iconSize = $strIconSize;

        return $this;
    }

    public function setIconAnchor($strIconAnchor)
    {
        $this->iconAnchor = $strIconAnchor;

        return $this;
    }

    public function setMarkerAction($strMarkerAction)
    {
        $this->markerAction = $strMarkerAction;

        return $this;
    }

    public function setUrl($strUrl)
    {
        $this->url = $strUrl;

        return $this;
    }

    public function setTarget($strTarget)
    {
        $this->target = $strTarget;

        return $this;
    }

    public function setInfoWindow($strInfoWindow)
    {
        $this->infoWindow = $strInfoWindow;

        return $this;
    }

    public function setInfoWindowAnchor($strInfoWindowAnchor)
    {
        $this->infoWindowAnchor = $strInfoWindowAnchor;

        return $this;
    }

    public function setInfoWindowSize($strInfoWindowSize)
    {
        $this->infoWindowSize = $strInfoWindowSize;

        return $this;
    }

    public function setInfoWindowMaxWidth($strInfoWindowMaxWidth)
    {
        $this->infoWindowMaxWidth = $strInfoWindowMaxWidth;

        return $this;
    }

    public function setStrokeColor($strStrokeColor)
    {
        $this->strokeColor = $strStrokeColor;

        return $this;
    }

    public function setStrokeOpacity($intStrokeOpacity)
    {
        $this->strokeOpacity = $intStrokeOpacity;

        return $this;
    }

    public function setStrokeWeight($intStrokeWeight)
    {
        $this->strokeWeight = $intStrokeWeight;

        return $this;
    }

    public function setFillColor($strFillColor)
    {
        $this->fillColor = $strFillColor;

        return $this;
    }

    public function setFillOpacity($intFillOpacity)
    {
        $this->fillOpacity = $intFillOpacity;

        return $this;
    }

    public function setKmlUrl($strUrl)
    {
        $this->kmlUrl = $strUrl;

        return $this;
    }

    public function setInverted($blnInverted)
    {
        $this->inverted = $blnInverted;

        return $this;
    }

    private static function init()
    {
        if (!in_array('dlh_googlemaps', \ModuleLoader::getActive()))
        {
            return false;
        }

        \Controller::loadLanguageFile('tl_dlh_googlemaps');

        return true;
    }

    /**
     * Set an object property
     *
     * @param string $strKey
     * @param mixed  $varValue
     */
    public function __set($strKey, $varValue)
    {
        $this->arrOptions[$strKey] = $varValue;
    }


    /**
     * Return an object property
     *
     * @param string $strKey
     *
     * @return mixed
     */
    public function __get($strKey)
    {
        if (isset($this->arrOptions[$strKey]))
        {
            return $this->arrOptions[$strKey];
        }

        return parent::__get($strKey);
    }


    /**
     * Check whether a property is set
     *
     * @param string $strKey
     *
     * @return boolean
     */
    public function __isset($strKey)
    {
        return isset($this->arrOptions[$strKey]);
    }


    public function getOptions()
    {
        return $this->arrOptions;
    }

}