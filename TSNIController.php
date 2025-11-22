<?php

namespace App\Http\Controllers\tsni;

use App\Http\Controllers\Controller;
use App\Models\tsni\LineSegment;
use Illuminate\Http\Request;

class TSNIController extends Controller
{
    public function getSegments(Request $request) {
        if (request('type') == "zone") {
            $validated = $request->validate([
                "lonLeft" => "required|numeric",
                "lonRight" => "required|numeric",
                "latBottom" => "required|numeric",
                "latTop" => "required|numeric"
            ]);

            $segments = LineSegment::where(function ($q) use ($validated)  {
                    // Select all segments with STARTING pos inside zone
                    $q->where('Xd', '>=', $validated['lonLeft'])
                    ->where('Xd', '<=', $validated['lonRight'])
                    ->where('Zd', '>=', $validated['latBottom'])
                    ->where('Zd', '<=', $validated['latTop']);
                })->orWhere(function ($q) use ($validated) {
                    // Or all segments with ENDING pos inside zone
                    $q->where('Xf', '>=', $validated['lonLeft'])
                    ->where('Xf', '<=', $validated['lonRight'])
                    ->where('Zf', '>=', $validated['latBottom'])
                    ->where('Zf', '<=', $validated['latTop']);
                })
                ->select('codeLigne', 'nomLigne', 'pkd', 'pkf')
                ->orderBy('nomLigne')
                ->orderBy('pkd')
                ->get();

            $linesDatas = [];
            foreach ($segments as $segment) {
                $existingLineIndex = array_search($segment->codeLigne, array_column($linesDatas, 'code'));
                if ($existingLineIndex !== false) {
                    $length = abs(intval(preg_replace("/-|\+/", "", $segment->pkf)) - intval(preg_replace("/-|\+/", "", $segment->pkd)));
                    $linesDatas[$existingLineIndex]["length"] += $length;
                } else {
                    $linesDatas[] = ["code" => $segment->codeLigne, "name" => $segment->nomLigne, "length" => 0];
                }
            }

            usort($linesDatas, function ($a, $b) {
                return $b['length'] <=> $a['length']; // DESC
            });

            return ["status" => "success", "nbResults" => count($segments), "linesDatas" => $linesDatas];
        } elseif (request('type') == "lines") {
            $validated = $request->validate([
                "names" => "required|array"
            ]);

            $segments = LineSegment::whereIn('nomLigne', $validated['names'])
                ->select('codeLigne', 'nomLigne', 'pkd', 'pkf')
                ->orderBy('nomLigne')
                ->orderBy('pkd')
                ->get();

            $linesDatas = [];
            foreach ($segments as $segment) {
                $existingLineIndex = array_search($segment->codeLigne, array_column($linesDatas, 'code'));
                if ($existingLineIndex !== false) {
                    $length = abs(intval(preg_replace("/-|\+/", "", $segment->pkf)) - intval(preg_replace("/-|\+/", "", $segment->pkd)));
                    $linesDatas[$existingLineIndex]["length"] += $length;
                } else {
                    $linesDatas[] = ["code" => $segment->codeLigne, "name" => $segment->nomLigne, "length" => 0];
                }
            }

            return ["status" => "success", "nbResults" => count($segments), "linesDatas" => $linesDatas];            
        } else {
            return ["status" => "error"];
        }
    }

    public function generatePreviewSvg(Request $request) {
        $validated = $request->validate([
            "latTop" => "required|numeric",
            "lonLeft" => "required|numeric",
            "latBottom" => "required|numeric",
            "lonRight" => "required|numeric",
            "selectedLines" => "nullable|array",
        ]);

        $linesInside = array_keys(array_filter($validated["selectedLines"], fn($l) => !$l["outside"]));
        $linesOutside = array_keys(array_filter($validated["selectedLines"], fn($l) => $l["outside"]));

        $segments = LineSegment::whereIn('nomLigne', $linesOutside)
            ->orWhere(function ($q) use ($validated, $linesInside) {
                // Select all line segments inside area
                $q->whereIn('nomLigne', $linesInside)
                ->where('Xf', '>=', $validated['lonLeft'])
                ->where('Xf', '<=', $validated['lonRight'])
                ->where('Zf', '>=', $validated['latBottom'])
                ->where('Zf', '<=', $validated['latTop']);
            })
            ->orWhere(function ($q) use ($validated)  {
                // Select all segments with STARTING pos inside zone
                $q->where('Xd', '>=', $validated['lonLeft'])
                ->where('Xd', '<=', $validated['lonRight'])
                ->where('Zd', '>=', $validated['latBottom'])
                ->where('Zd', '<=', $validated['latTop']);
            })->orWhere(function ($q) use ($validated) {
                // Or all segments with ENDING pos inside zone
                $q->where('Xf', '>=', $validated['lonLeft'])
                ->where('Xf', '<=', $validated['lonRight'])
                ->where('Zf', '>=', $validated['latBottom'])
                ->where('Zf', '<=', $validated['latTop']);
            })
            ->get();

        $Xd = array();
        $Zd = array();
        $Xf = array();
        $Zf = array();
        $codeLigneColor = array();
        $nomLigne = array();
        
        foreach ($segments as $segment) {
            $Xd[] = $segment->Xd;
			$Zd[] = $segment->Zd;
			$Xf[] = $segment->Xf;
			$Zf[] = $segment->Zf;
			$colorHue = substr(md5($segment->nomLigne), 0, 2);
			$codeLigneColor[] = hexdec($colorHue) % 360;
			$nomLigne[] = $segment->nomLigne;
        }

        // Get the min values in $Xd and $Xf
        $minX = min(min($Xd), min($Xf));
        // Get the max values in $Xd and $Xf
        $maxX = max(max($Xd), max($Xf));
        // Get the min values in $Zd and $Zf
        $minZ = min(min($Zd), min($Zf));
        // Get the max values in $Zd and $Zf
        $maxZ = max(max($Zd), max($Zf));

        $offsetZ = $maxZ;
        $offsetX = $minX;

        $width = $maxX - $minX;
        $height = $maxZ - $minZ;
        // $aspectRatio = $width / $height;

        $svgCode = "<svg id='generatedPreview' height='300' width='300' viewBox='0 0 300 300' xmlns='http://www.w3.org/2000/svg'>";
        $counter = 0;
        foreach ($Xd as $key => $value) {
            $svgCode .= '<line x1="'.(($Xd[$key]-$offsetX) /$width *100).'%" y1="'.(($maxZ - $Zd[$key]) /$height *100).'%" x2="'.(($Xf[$key]-$offsetX) /$width*100).'%" y2="'.(($maxZ - $Zf[$key]) /$height *100).'%" style="stroke:hsl('.$codeLigneColor[$key].',100%, 50%);stroke-width: min(0.5vh, 5px); stroke-linecap: round;" data-lineName="'.$nomLigne[$key].'"/>';
            $counter = ($counter+1) % 255;
        }
        $svgCode .= '</svg>';

        return $svgCode;
    }
}