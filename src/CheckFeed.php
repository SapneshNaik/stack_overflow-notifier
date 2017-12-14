<?php namespace KernelDev;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use DateTime;

class CheckFeed extends CommonTasks
{


    private $questionNumber = '';
    private $questionExists;
    private $shouldNotify;

    /**
     * Configure the command.
     */
    public function configure()
    {
        $this->setName('run')
             ->setDescription('Check for new questions');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
    
        $this->isConnected($output);
        $FeedURLs = $this->getFeedURLs($output);

        $tagQuestions = array();

        foreach ($FeedURLs as $FeedURL) {
            $xml = @simplexml_load_file($FeedURL);
            array_push($tagQuestions, $xml);
        }

        foreach ($tagQuestions as $tagQuestion) {
            foreach ($tagQuestion->entry as $entry) {
                $this->getQuestionNumber($entry->id)
                     ->questionExists()
                     ->shouldPersist()
                     ->shouldNotify($entry);
            }
        }
    }

    /**
     * Return RSS feed URL.
     *
     * @param OutputInterface $output
     * @return mixed
     */
    protected function getFeedURLs(OutputInterface $output)
    {
        $tags = $this->database->fetchAll('tags');
        
        if ($tags) {
            $feedURLs = array();

            foreach ($tags as $tag) {
                $feedURL = sprintf("https://stackoverflow.com/feeds/tag?tagnames=".$tag['title']."&sort=newest");

                array_push($feedURLs, $feedURL);
            }
            return $feedURLs;
        } else {
            $this->output($output, 'You have not subscribed to any tags!! Exiting now..', 'error');

            exit(1);
        }
    }


    protected function getQuestionNumber($path)
    {

        $this->questionNumber = basename($path);
        return $this;
    }

    protected function questionExists()
    {
        
        $IDq = $this->database->checkField($this->questionNumber);
        
        if (!empty($IDq)) {
            $this->questionExists =true;
            return $this;
        } else {
            $this->questionExists =false;
            return $this;
        }
    }

    protected function shouldPersist()
    {
     
        if (!$this->questionExists) {
            $questionNumber = $this->questionNumber;
            $IDq = $this->database->query(
                'insert into questions(question_number) values(:questionNumber)',
                compact('questionNumber')
            );
            $this->shouldNotify = true;
        } else {
            $this->shouldNotify = false;
        }

        return $this;
    }

    public function shouldNotify($entry)
    {

        if ($this->shouldNotify) {
            exec(sprintf('notify-send  "'.$entry->title.'"  "'.$entry->link->attributes()->href.'"'));
        }
    }
}
