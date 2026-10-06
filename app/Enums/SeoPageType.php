<?php

namespace App\Enums;

enum SeoPageType: string
{
    case Home = 'home';
    case Hotels = 'hotels';
    case Rooms = 'rooms';
    case Gallery = 'gallery';
    case AboutUs = 'about-us';
    case ContactUs = 'contact-us';
    case HelpSupport = 'help-support';
    case HelpSupportFaq = 'help-support-faq';
    case Blogs = 'blogs';
    case ListProperty = 'list-property';

    public function getLabel(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::Hotels => 'Property',
            self::Rooms => 'Rooms',
            self::Gallery => 'Gallery',
            self::AboutUs => 'About Us',
            self::ContactUs => 'Contact Us',
            self::HelpSupport => 'Help & Support',
            self::HelpSupportFaq => 'Help & Support FAQ',
            self::Blogs => 'Blogs',
            self::ListProperty => 'List Property',
        };
    }
}
