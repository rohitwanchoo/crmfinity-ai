<?php

namespace App\Http\Controllers;

use App\Models\LearnedPattern;
use App\Models\MerchantProfile;
use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TrainingController extends Controller
{
    public function index()
    {
        $stats = [
            'total_sessions' => TrainingSession::count(),
            'total_patterns' => LearnedPattern::count(),
            'total_merchants' => MerchantProfile::count(),
            'recent_sessions' => TrainingSession::with('user')
                ->latest()
                ->take(10)
                ->get(),
        ];

        return view('training.index', compact('stats'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'bank_name' => 'required|string|max:255',
            'statement_pdfs.*' => 'required|file|mimes:pdf|max:10240',
            'scorecard_pdf' => 'nullable|file|mimes:pdf|max:10240',
            'fcs_pdf' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $sessionId = 'train_'.date('Ymd_His').'_'.Str::random(8);

        // Create training session
        $session = TrainingSession::create([
            'session_id' => $sessionId,
            'user_id' => Auth::id(),
            'bank_name' => $request->bank_name,
            'bank_type' => $request->bank_name,
            'processing_status' => 'pending',
        ]);

        // Handle file uploads
        $uploadPath = storage_path('app/uploads');

        if ($request->hasFile('statement_pdfs')) {
            foreach ($request->file('statement_pdfs') as $index => $file) {
                $filename = $sessionId.'_stmt_'.$index.'.pdf';
                $file->move($uploadPath, $filename);

                if ($index === 0) {
                    $session->statement_pdf_path = $uploadPath.'/'.$filename;
                }
            }
        }

        if ($request->hasFile('scorecard_pdf')) {
            $filename = $sessionId.'_scorecard.pdf';
            $request->file('scorecard_pdf')->move($uploadPath, $filename);
            $session->scorecard_pdf_path = $uploadPath.'/'.$filename;
        }

        if ($request->hasFile('fcs_pdf')) {
            $filename = $sessionId.'_fcs.pdf';
            $request->file('fcs_pdf')->move($uploadPath, $filename);
            $session->fcs_pdf_path = $uploadPath.'/'.$filename;
        }

        $session->save();

        // TODO: Dispatch job to process training session
        // ProcessTrainingSession::dispatch($session);

        return redirect()->route('training.index')
            ->with('success', 'Training session created successfully. Processing...');
    }
}
