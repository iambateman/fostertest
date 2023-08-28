<?php

use App\Actions\GetAIWithFallback;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;

new class extends Component {

    public int $age = 0;
    public string $name = '';
    public string $address = '';


    public string $query = '';
    public string $response = '';

    public Collection $queries;

    public function mount()
    {
        $this->queries = collect([]);
    }

    public function submit()
    {
        // Make sure the query isn't a million words long.
        if (str($this->query)->wordCount() > 40) {
            $this->response = "Sorry, this is super long.";
            return;
        }

        // build the prompt
        $prompt = $this->buildPrompt();

        // Run the query
        $response = GetAIWithFallback::run($prompt, 'gpt-4');

        $this->parseResponse($response);


        // remove the query from the textbox
        $this->query = '';

    }

    protected function buildPrompt(): string
    {

        $prompt = "You are a personal assistant helping someone schedule a pickup time, with a child's name, age, and address.";

        $prompt .= $this->noteAlreadyReceivedInfo($prompt);
//        $prompt .= $this->getPreviousConversation($prompt);

        $prompt .= " This is what the user just said: {{ $this->query }}.";

        if ($this->response) {
            $prompt .= "\nAnd this is what you just said: {{ $this->response }}.";
        }

        $prompt .= "\n\n";
        $prompt .= "Try to get the information which is not yet available. If you have all the information, respond letting them know you got everything.";
        $prompt .= " We only need first name, not full name.";
        $prompt .= " Addresses are often just street addresses, not full mailing addresses.";
        $prompt .= " Only accept requests which are relevant to getting name, age, and address. Any other requests/questions must be politely rejected. If you need to reject, use the message 'Sorry, that is out of scope for my responsibilities.'";

        $prompt .= "Respond only with this format:";
        $prompt .= "-----\n";
        $prompt .= "NAME:\n";
        $prompt .= "AGE:\n";
        $prompt .= "ADDRESS:\n";
        $prompt .= "MESSAGE:\n";
        $prompt .= "-----";

        return $prompt;
    }

    protected function getPreviousConversation($prompt): string|null
    {

        if ($this->queries->isNotEmpty()) {
            $prompt .= " Conversation history is below:\n";

            $this->queries->each(function (string $query) use ($prompt) {
                $prompt .= "";
            });
        }

        return $prompt;
    }

    protected function noteAlreadyReceivedInfo($prompt): string|null
    {
        if ($this->age == 0 && !$this->address && !$this->name) {
            return null;
        }

        $alreadyHave = collect([
            ($this->age > 0) ? $this->age : null,
            ($this->name) ?: null,
            ($this->address) ?: null,
        ]);

        $prompt .= " You already have " . $alreadyHave->filter()->implode(', ');

        return $prompt;
    }

    protected function parseResponse(string $response): self
    {
        info($response);

        $response = str($response);

        if ($this->age == 0 && $response->contains('AGE:')) {
            $this->age = $response->match('/AGE:\s*(\d+)/')->toInteger();
        }

        if (!$this->name && $response->contains('NAME:')) {
            $this->name = $response->match('/NAME:\s*([^\n]+)/')->toString();
        }

        if (!$this->address && $response->contains('ADDRESS:')) {
            $address = $response->match('/ADDRESS:\s*([^\r\n]+)/')->toString();

            // Regex EOL issue.
            if(! str($address)->contains('MESSAGE:')) {
                $this->address = $address;
            }
        }

        if ($response->contains('MESSAGE:')) {
            $this->response = $response->match('/MESSAGE:\s*([^\r\n]+)/')->toString();
        } else {
            $this->response = "Hmm, something went wrong. Sorry";
        }

        return $this;
    }

}

?>

<div class="min-h-screen bg-gray-100 pt-16">
    <div class="max-w-2xl bg-white mx-auto shadow-md rounded-sm p-8">
        <div class="text-xl border-b border-gray-100 mb-16">
            <p class="mb-4"><strong class="font-semibold">Age: </strong> {{ $age }}</p>
            <p class="mb-4"><strong class="font-semibold">Name: </strong> {{ $name }}</p>
            <p class="mb-4"><strong class="font-semibold">Address: </strong> {{ $address }}</p>
        </div>

        <div class="bg-green-50 mb-4 p-8">
            <h3 class="font-semibold">Response: {{ $response }}</h3>
        </div>

        <div>
            <form action="" wire:submit.prevent="submit">
                <textarea name="" id="" wire:model.defer="query" cols="30" rows="10"
                          class="mb-2 w-full rounded-md border-gray-200 text-xl focus:border-brand-500 flex-1 p-3 font-500 text-brand-800 block w-full transition duration-150 ease-in-out sm:leading-5 focus:outline-none focus:ring focus:ring-brand-400"></textarea>
                <button type="submit"
                        class="rounded-md bg-gray-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-gray-900 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Send
                </button>
            </form>
        </div>
    </div>
</div>
