<?php

namespace App\Classes;

class SeasonsSelector
{

    /**
     * Returns HTML <option></option> tags to show a list of seasons (i.e. 2023-2024)
     * Define the amount of seasons to be returned in the parameter. 3 will be 
     * returned by default.
     *
     * @param integer $quantity
     * @return string
     */
    static public function GetSeasonsHTMLDropdown(int $quantity = 3): string
    {

        // Set up first reference season start & end year.
        $start_year = date('m') <= 6 ? date('Y') - 1 : date('Y');
        $end_year = date('m') <= 6 ? date('Y') : date('Y') + 1;
        $html_code = '';

        // return expected seasons
        for($i = 0; $i < $quantity ; $i++) {
            $html_code .= '<option value = "' . $start_year + $i . ' - ' . $end_year + $i . '">' . $start_year + $i . ' - ' . $end_year + $i . '</option>' . PHP_EOL;
        }

        return $html_code;
    }
}
