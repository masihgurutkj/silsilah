<?php

namespace App\Http\Controllers;

use DB;
use Storage;
use App\User;
use App\Couple;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    /**
     * Search user by keyword.
     *
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        $q = $request->get('q');
        $users = [];

        if ($q) {
            $users = User::with('father', 'mother')->where(function ($query) use ($q) {
                $query->where('name', 'like', '%'.$q.'%');
                $query->orWhere('nickname', 'like', '%'.$q.'%');
            })
                ->orderBy('name', 'asc')
                ->paginate(24);
        }

        return view('users.search', compact('users'));
    }

    /**
     * Display the specified User.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        $usersMariageList = [];
        foreach ($user->couples as $spouse) {
            $usersMariageList[$spouse->pivot->id] = $user->name.' & '.$spouse->name;
        }

        $allMariageList = [];
        foreach (Couple::with('husband', 'wife')->get() as $couple) {
            $allMariageList[$couple->id] = $couple->husband->name.' & '.$couple->wife->name;
        }

        $malePersonList = User::where('gender_id', 1)->pluck('nickname', 'id');
        $femalePersonList = User::where('gender_id', 2)->pluck('nickname', 'id');

        return view('users.show', [
            'user'             => $user,
            'usersMariageList' => $usersMariageList,
            'malePersonList'   => $malePersonList,
            'femalePersonList' => $femalePersonList,
            'allMariageList'   => $allMariageList,
        ]);
    }

    /**
     * Display the user's family chart.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function chart(User $user)
    {
        $father = $user->father_id ? $user->father : null;
        $mother = $user->mother_id ? $user->mother : null;

        $fatherGrandpa = $father && $father->father_id ? $father->father : null;
        $fatherGrandma = $father && $father->mother_id ? $father->mother : null;

        $motherGrandpa = $mother && $mother->father_id ? $mother->father : null;
        $motherGrandma = $mother && $mother->mother_id ? $mother->mother : null;

        $childs = $user->childs;
        $colspan = $childs->count();
        $colspan = $colspan < 4 ? 4 : $colspan;

        $siblings = $user->siblings();
        return view('users.chart', compact('user', 'childs', 'father', 'mother', 'fatherGrandpa', 'fatherGrandma', 'motherGrandpa', 'motherGrandma', 'siblings', 'colspan'));
    }

    /**
     * Show user family tree
     * @param  User   $user
     * @return \Illuminate\Http\Response
     */
    public function tree(User $user)
    {
        return view('users.tree', compact('user'));
    }

    /**
     * Show the form for editing the specified User.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        $this->authorize('edit', $user);

        $replacementUsers = [];
        if (request('action') == 'delete') {
            $replacementUsers = User::where('gender_id', $user->gender_id)->pluck('nickname', 'id');
        }

        return view('users.edit', compact('user', 'replacementUsers'));
    }

    /**
     * Update the specified User in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $this->validate($request, [
            'nickname'  => 'required|string|max:255',
            'name'      => 'required|string|max:255',
            'gender_id' => 'required|numeric',
            'dob'       => 'nullable|date|date_format:Y-m-d',
            'dod'       => 'nullable|date|date_format:Y-m-d',
            'yod'       => 'nullable|date_format:Y',
            'phone'     => 'nullable|string|max:255',
            'address'   => 'nullable|string|max:255',
            'city'      => 'nullable|string|max:255',
            'email'     => 'nullable|string|max:255',
            'password'  => 'nullable|min:6|max:15',
        ]);

        $user->nickname = $request->nickname;
        $user->name = $request->get('name');
        $user->gender_id = $request->get('gender_id');
        $user->dob = $request->get('dob');
        $user->dod = $request->get('dod');

        if ($request->get('dod')) {
            $user->yod = substr($request->get('dod'), 0, 4);
        } else {
            $user->yod = $request->get('yod');
        }

        $user->phone = $request->get('phone');
        $user->address = $request->get('address');
        $user->city = $request->get('city');
        $user->email = $request->get('email');

        if ($request->get('password')) {
            $user->password = bcrypt($request->get('password'));
        }

        $user->save();

        return redirect()->route('users.show', $user->id);
    }

    /**
     * Remove the specified User from storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User $user
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        if ($request->has('replace_delete_button')) {
            $attributes = $request->validate([
                'replacement_user_id' => 'required|exists:users,id',
            ], [
                'replacement_user_id.required' => __('validation.user.replacement_user_id.required'),
            ]);

            DB::beginTransaction();
            $oldUserId = $user->id;

            DB::table('users')->where('father_id', $oldUserId)->update([
                'father_id' => $attributes['replacement_user_id'],
            ]);

            DB::table('users')->where('mother_id', $oldUserId)->update([
                'mother_id' => $attributes['replacement_user_id'],
            ]);

            DB::table('users')->where('manager_id', $oldUserId)->update([
                'manager_id' => $attributes['replacement_user_id'],
            ]);

            DB::table('couples')->where('husband_id', $oldUserId)->update([
                'husband_id' => $attributes['replacement_user_id'],
            ]);

            DB::table('couples')->where('wife_id', $oldUserId)->update([
                'wife_id' => $attributes['replacement_user_id'],
            ]);

            DB::table('couples')->where('manager_id', $oldUserId)->update([
                'manager_id' => $attributes['replacement_user_id'],
            ]);

            $user->delete();
            DB::commit();

            return redirect()->route('users.show', $attributes['replacement_user_id']);
        }

        request()->validate([
            'user_id' => 'required',
        ]);

        if (request('user_id') == $user->id && $user->delete()) {
            return redirect()->route('users.search');
        }

        return back();
    }

    /**
     * Upload users photo.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User                $user
     *
     * @return \Illuminate\Http\Response
     */
    public function photoUpload(Request $request, User $user)
    {
        $request->validate([
            'photo' => 'required|image|max:200',
        ]);

        $storage = env('APP_ENV') == 'testing' ? 'avatars' : 'public';

        if (Storage::disk($storage)->exists($user->photo_path)) {
            Storage::disk($storage)->delete($user->photo_path);
        }

        $user->photo_path = $request->photo->store('images', $storage);
        $user->save();

        return back();
    }
}
