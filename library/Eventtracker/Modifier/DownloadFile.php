<?php

namespace Icinga\Module\Eventtracker\Modifier;

class DownloadFile extends DownloadUrl
{
    protected static $name = 'Download a file from a URL';

    public function transform($object, string $propertyName)
    {
        $value = ObjectUtils::getSpecificValue($object, $propertyName);

        if ($value === null) {
            return null;
        }

        $data = $this->simpleTransform($value);
        if (iconv('UTF-8', 'UTF-8//IGNORE', $data) !== $data) {
            $data = 'base64,' . base64_encode($data);
        }

        $url = parse_url($value);

        if (! isset($object->files)) {
            $object->files = [];
        }

        $object->files[] = (object)[
            'name' => basename($url['path']),
            'data' => $data
        ];

        return $value;
    }
}
