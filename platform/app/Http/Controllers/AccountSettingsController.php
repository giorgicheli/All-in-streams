<?php

namespace App\Http\Controllers;

use App\Rules\TelegramHandle;
use Illuminate\Http\Request;

class AccountSettingsController extends Controller
{
    public function edit(Request $request)
    {
        return view('portal.account-settings');
    }

    public function update(Request $request)
    {
        $data = $request->validate(['telegram' => ['required', 'string', 'max:100', new TelegramHandle]]);
        $request->user()->update(['telegram' => TelegramHandle::canonicalize($data['telegram'])]);
        return to_route('account.settings')->with('status', 'Your private Telegram contact was updated. Only All In Streams staff can see it.');
    }
}
