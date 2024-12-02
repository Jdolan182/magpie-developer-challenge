<?php

namespace App;

use Symfony\Component\DomCrawler\Crawler;

require 'vendor/autoload.php';

class Scrape
{
    private array $products = [];

    public function run(): void
    {
        $linkScrape = ScrapeHelper::fetchDocument('https://www.magpiehq.com/developer-challenge/smartphones');

        $links = $linkScrape->filter('#pages > div > a')->links();
        
        $page = 1;
        foreach($links as $link)
        {
            
            //var_dump($link->getUri()); die;
            //When I do this I get https://www.magpiehq.com/smartphones?page=1
            //Rather than the challenge url so I just did used this instead
            //Same thing happened with the image so not sure if I'm using the wrong thing for urls or not
            $productScrape = ScrapeHelper::fetchDocument('https://www.magpiehq.com/developer-challenge/smartphones?page=' . $page);

            $productDivs = $productScrape->filter('.product');


            $productDivs->each(function (Crawler $product){

                $numbers = preg_replace('/[^0-9]/', '', $product->filter('.product-capacity')->text());
                $letters = preg_replace('/[^a-zA-Z]/', '', $product->filter('.product-capacity')->text());

                if($letters == 'GB')
                {
                    $numbers = $numbers * 1000;
                }
                $capacityMB = $numbers;

                $availabilityAndShippingText = $product->filter('div.my-4.text-sm');

                $availability = [];
                $availability[0] = '';
                $availability[1] = '';
                $availability['isAvailable'] = false;
                $availability['shippingData'] = '';

                foreach($availabilityAndShippingText as  $index => $availabilityAndShipping){
                    $availability[$index] = trim($availabilityAndShipping->textContent);
                }

                if(str_contains($availability[0], 'In Stock'))
                {
                    $availability['isAvailable'] = true;
                }


                if(isset($availability[1]))
                {
                    $dates = [];
                    preg_match_all('/(\d{1,2}) (\w+) (\d{4})/', $availability[1], $dates);
                }

                if(isset($dates[0][0]))
                {
                    $time = strtotime($dates[0][0]);
                    
                    $availability['shippingData'] = date('Y-m-d', $time);
                }
                
                $colours = $product->filter('span.rounded-full');
                $colours->each(function (Crawler $colour) use ($product, $capacityMB, $availability) {

                    $productName = $product->filter('.product-name')->text();
                    $productColour =  $colour->attr('data-colour');

                    //Did this on the duplicate because it had different colours available
                    //Not sure if that is because the data on the generated at random or not but this basically includes the other colour
                    $existsAlready = array_filter($this->products, function($products) use ($productName, $productColour, $capacityMB) {
                        return $productName == $products['title'] && $productColour == $products['colour']  && $capacityMB == $products['capacityMB']; 
                    });

                   
                    if(count($existsAlready) == 0){

                        $this->products[] = [
                            'title'            => $productName,
                            'price'            => $product->filter('div.my-8.block')->text(),
                            'imageUrl'         => $product->filter('img')->image()->getUri(),
                            'capacityMB'       => $capacityMB,
                            'colour'           => $productColour,
                            'availabilityText' => $availability['0'],
                            'isAvailable'      => $availability['isAvailable'],
                            'shippingText'     => $availability['1'],
                            'shippingData'     => $availability['shippingData']
                        ];
                    }
                    
                });
            });

            $page++;
        
          
        }
        file_put_contents('output.json', json_encode($this->products));
    }
}

$scrape = new Scrape();
$scrape->run();
