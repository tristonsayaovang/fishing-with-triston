<?php

namespace Drupal\fishing_with_triston_utility\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;
use Drupal\media\Entity\Media;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Provides custom Twig functions for Fishing With Triston.
 */
class FishingWithTristonTwigExtension extends AbstractExtension
{



    protected FileUrlGeneratorInterface $fileUrlGenerator;

    public function __construct(FileUrlGeneratorInterface $file_url_generator)
    {
        $this->fileUrlGenerator = $file_url_generator;
    }
    public function getFunctions()
    {
        return [
            new TwigFunction('hasRecommendedLures', [$this, 'hasRecommendedLures']),
            new \Twig\TwigFunction('renderMediaImage', [$this, 'renderMediaImage'], ['is_safe' => ['html']]),

        ];
    }


    public function getFilters()
    {
        return [
            //   new TwigFilter('clean_html', [$this, 'cleanHtml']),
        ];
    }

    public function hasRecommendedLures($node): bool
    {

        // Ensure this is a node and of the expected content type.
        if (!$node || $node->getEntityTypeId() !== 'node') {
            return FALSE;
        }

        if ($node->bundle() !== 'location') {
            // Only check for 'location' nodes — change as needed.
            return FALSE;
        }

        // List of fields to check for this content type.
        $fields_to_check = [
            'field_location_spring_lures',
            'field_location_summer_lures',
            'field_recommended_lures_copy',
            'field_location_fall_lures',
        ];

        // Loop through each field.
        foreach ($fields_to_check as $field_name) {
            if ($node->hasField($field_name)) {
                $field = $node->get($field_name);
                if (!$field->isEmpty()) {
                    return TRUE;
                }
            }
        }

        // If none of the fields have content, return FALSE.
        return FALSE;
    }


    /**
     * Render an <img> tag from a media reference field.
     *
     * @param \Drupal\Core\Field\FieldItemListInterface $field
     *   The media reference field (e.g. $node->get('field_hero_image')).
     * @param string|null $style_name
     *   (optional) The machine name of an image style. Defaults to original image.
     *
     * @return string
     *   The rendered <img> tag, or an empty string if not available.
     */
    public function renderMediaImage($field, ?string $style_name = NULL): string
    {
        // Validate the field.
        if (!$field || $field->isEmpty()) {
            return $this->renderDefaultImage($style_name);
        }

        // Get the referenced media entity.
        $media = $field->entity ?? NULL;
        if (!$media instanceof Media) {
            return $this->renderDefaultImage($style_name);
        }

        // Get the media’s image file entity.
        if (!$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
            return $this->renderDefaultImage($style_name);
        }
        $image_file = $media->get('field_media_image')->entity;
        if (!$image_file) {
            return $this->renderDefaultImage($style_name);
        }

        // Build the image URL.
        $uri = $image_file->getFileUri();
        $url = $style_name && ImageStyle::load($style_name)
            ? ImageStyle::load($style_name)->buildUrl($uri)
            : $this->fileUrlGenerator->generateAbsoluteString($uri);

        // Get alt text if available.
        $alt = $media->hasField('field_media_image')
            ? $media->get('field_media_image')->alt
            : '';

        // Return a safe <img> tag.
        return sprintf('<img src="%s" alt="%s" />', $url, htmlspecialchars($alt, ENT_QUOTES, 'UTF-8'));
    }

    protected function renderDefaultImage(?string $style_name = NULL, string $filename = 'image-coming-soon.jpg'): string
    {
        $uri = 'public://defaults/' . $filename; // Public file system URI

        // Apply image style if provided
        if ($style_name && ImageStyle::load($style_name)) {
            $url = ImageStyle::load($style_name)->buildUrl($uri);
        } else {
            // Fallback to absolute URL
            $url = $this->fileUrlGenerator->generateAbsoluteString($uri);
        }

        return sprintf('<img src="%s" alt="Default image" />', $url);
    }
}
