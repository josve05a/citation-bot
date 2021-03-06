<?php

/*
 * Current tests that are failing.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class expandFnsTest extends testBaseClass {

  public function testCapitalization() {
    $this->assertSame('Molecular and Cellular Biology', 
                        title_capitalization(title_case('Molecular and cellular biology'), TRUE));
    $this->assertSame('z/Journal', 
                        title_capitalization(title_case('z/Journal'), TRUE));
    $this->assertSame('The Journal of Journals', // The, not the
                        title_capitalization('The Journal Of Journals', TRUE));
    $this->assertSame('A Journal of Chemistry A',
                        title_capitalization('A Journal of Chemistry A', TRUE));
    $this->assertSame('A Journal of Chemistry E',
                        title_capitalization('A Journal of Chemistry E', TRUE));                      
    $this->assertSame('This a Journal', 
                        title_capitalization('THIS A JOURNAL', TRUE));
    $this->assertSame('This a Journal', 
                        title_capitalization('THIS A JOURNAL', TRUE));
    $this->assertSame("THIS 'A' JOURNAL mittEilUngen", 
                        title_capitalization("THIS `A` JOURNAL mittEilUngen", TRUE));
    $this->assertSame('[Johsnon And me]', title_capitalization('[Johsnon And me]', TRUE)); // Do not touch links
  }
  
  public function testFrenchCapitalization() {
    $this->assertSame("L'Aerotecnica",
                        title_capitalization(title_case("L'Aerotecnica"), TRUE));
    $this->assertSame("Phénomènes d'Évaporation d'Hydrologie",
                        title_capitalization(title_case("Phénomènes d'Évaporation d’hydrologie"), TRUE));
    $this->assertSame("D'Hydrologie Phénomènes d'Évaporation d'Hydrologie l'Aerotecnica",
                        title_capitalization("D'Hydrologie Phénomènes d&#x2019;Évaporation d&#8217;Hydrologie l&rsquo;Aerotecnica", TRUE));
  }
  
  public function testITS() {
    $this->assertSame(                     "Keep case of its Its and ITS",
                        title_capitalization("Keep case of its Its and ITS", TRUE));
    $this->assertSame(                     "ITS Keep case of its Its and ITS",
                        title_capitalization("ITS Keep case of its Its and ITS", TRUE));
  }
    
  public function testExtractDoi() {
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', 
                        extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full')[1]);
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', 
                        extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract')[1]);
    $this->assertSame('10.1016/j.physletb.2010.03.064', 
                        extract_doi(' 10.1016%2Fj.physletb.2010.03.064')[1]);
    $this->assertSame('10.1093/acref/9780199204632.001.0001', 
                        extract_doi('http://www.oxfordreference.com/view/10.1093/acref/9780199204632.001.0001/acref-9780199204632-e-4022')[1]);
    $this->assertSame('10.1038/nature11111', 
                        extract_doi('http://www.oxfordreference.com/view/10.1038/nature11111/figures#display.aspx?quest=solve&problem=punctuation')[1]);
  }
  
  public function testTidyDate() {
    $this->assertSame('2014', tidy_date('maanantai 14. heinäkuuta 2014'));
    $this->assertSame('2012-04-20', tidy_date('2012年4月20日 星期五'));
    $this->assertSame('2011-05-10', tidy_date('2011-05-10T06:34:00-0400'));
    $this->assertSame('July 2014', tidy_date('2014-07-01T23:50:00Z, 2014-07-01'));
    $this->assertSame('', tidy_date('۱۳۸۶/۱۰/۰۴ - ۱۱:۳۰'));
    $this->assertSame('2014-01-24', tidy_date('01/24/2014 16:01:06'));
    $this->assertSame('2011-11-30', tidy_date('30/11/2011 12:52:08'));
    $this->assertSame('2011'      , tidy_date('05/11/2011 12:52:08'));
    $this->assertSame('2011-11-11', tidy_date('11/11/2011 12:52:08'));
    $this->assertSame('2018-10-21', tidy_date('Date published (2018-10-21'));
    $this->assertSame('2008-04-29', tidy_date('07:30 , 04.29.08'));
    $this->assertSame('', tidy_date('-0001-11-30T00:00:00+00:00'));
    $this->assertSame('', tidy_date('22/22/2010'));  // That is not valid date code
    $this->assertSame('', tidy_date('The date is 88 but not three')); // Not a date, but has some numbers
  }
  
  public function testRemoveComments() {
    $this->assertSame('ABC', remove_comments('A<!-- -->B# # # CITATION_BOT_PLACEHOLDER_COMMENT 33 # # #C'));
  } 
}    
