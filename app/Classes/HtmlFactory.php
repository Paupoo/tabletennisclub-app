<?php

namespace App\Classes;

class HtmlFactory
{

    /**
     * Returns HTML <option></option> tags to show a list of seasons (i.e. 2023-2024)
     * Define the amount of seasons to be returned in the parameter. 3 will be 
     * returned by default.
     *
     * @param integer $quantity
     * @return string
     */
    static public function SeasonsInHTMLList(int $quantity = 3): string
    {
        $html_code = '';

        // Set up first reference season start & end year.
        $start_year = (date('m') <= 6) // If the date
            ? date('Y') - 1 // is between January and June included
            : date('Y'); // is between July and December included
        $end_year = $start_year +1;
      

        // return expected seasons
        for ($i = 0; $i < $quantity; $i++) {
            $html_code .= self::wrapIntoOptionTag($start_year + $i . ' - ' . $end_year + $i);
        }

        return $html_code;
    }

    /**
     * Returns HTML <option></option> tags to show a list of team names (A, B, C...)
     *
     * @return string
     */
    static public function TeamNamesInHTMLList(): string
    {
        $letter = 'A';
        $html_code = '';

        for ($i = 0; $i < 26; $i++) {
            $html_code .= self::wrapIntoOptionTag($letter);
            $letter++;
        }

        return $html_code;
    }

    /**
     * Returns HTML <option></option> tags to show a list of competitions types (men, women, veterans...)
     *
     * @return string
     */
    static public function competitionTypesInHtmlList(): string
    {
        $html_code = '';

        $competitions_types = [
            ['men',4],
            ['women',3],
            ['veterans',3],
        ];

        foreach ($competitions_types as $type) {
            $html_code .= self::wrapIntoOptionTag($type[1], ucfirst($type[0]));
        }

        return $html_code;
    }

    /**
     * Returns HTML <option value="$value">$text</option>
     *
     * @param string $value
     * @param string $text
     * @return string
     */
    public static function wrapIntoOptionTag(string $value, string $text = ''): string
    {

        return ($text === '') // If the second argument
            ? sprintf('<option value="%1$s">%1$s</options>' . PHP_EOL, $value)          // is the same as the value
            : sprintf('<option value="%1$s">%2$s</options>' . PHP_EOL, $value, $text);  // is different as the value
    }
    
}
